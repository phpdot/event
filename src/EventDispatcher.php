<?php

declare(strict_types=1);

/**
 * PSR-14 event dispatcher with sync/async support, ordering, and logging.
 *
 * Sync listeners are resolved from the PSR-11 container and called directly.
 * Async listeners are published to a queue via AsyncDispatcherInterface.
 * Each listener execution is logged via PSR-3 LoggerInterface.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event;

use PHPdot\Event\Contract\AsyncDispatcherInterface;
use PHPdot\Event\DTO\ListenerEntry;
use PHPdot\Event\Exception\AsyncDispatchException;
use PHPdot\Event\Exception\ListenerException;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Log\LoggerInterface;

final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * Wire the dispatcher to its listener source, container, async backend, and logger.
     *
     * @param ListenerProvider $provider Supplies the ordered listeners for an event
     * @param ContainerInterface $container Resolves handler classes to instances
     * @param AsyncDispatcherInterface $async Queues listeners marked async
     * @param LoggerInterface $logger Records listener failures
     */
    public function __construct(
        private readonly ListenerProvider $provider,
        private readonly ContainerInterface $container,
        private readonly AsyncDispatcherInterface $async,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Dispatch an event to all relevant listeners.
     *
     * PSR-14 compliant:
     * - Calls all listeners from the provider
     * - Checks StoppableEventInterface after each listener
     * - Returns the (possibly modified) event object
     */
    public function dispatch(object $event): object
    {
        if ($event instanceof StoppableEventInterface) {
            return $this->dispatchStoppable($event);
        }

        foreach ($this->provider->getListenersForEvent($event) as $entry) {
            $this->dispatchEntry($event, $entry);
        }

        return $event;
    }

    /**
     * Dispatch a stoppable event, checking propagation after each listener.
     *
     * @param StoppableEventInterface $event
     *
     * @return object
     */
    private function dispatchStoppable(StoppableEventInterface $event): object
    {
        if ($event->isPropagationStopped()) {
            return $event;
        }

        foreach ($this->provider->getListenersForEvent($event) as $entry) {
            if ($event->isPropagationStopped()) {
                break;
            }

            $this->dispatchEntry($event, $entry);
        }

        return $event;
    }

    /**
     * Dispatch a single listener entry (sync or async).
     *
     * @param object $event
     * @param ListenerEntry $entry
     *
     * @return void
     */
    private function dispatchEntry(object $event, ListenerEntry $entry): void
    {
        if (!$entry->enabled) {
            return;
        }

        if ($entry->async) {
            $this->dispatchAsync($event, $entry);
        } else {
            $this->dispatchSync($event, $entry);
        }
    }

    /**
     * Resolve and execute a sync listener.
     *
     * @param object $event
     * @param ListenerEntry $entry
     *
     * @throws ListenerException If the handler fails to resolve or execute
     *
     * @return void
     */
    private function dispatchSync(object $event, ListenerEntry $entry): void
    {
        try {
            $handler = $this->container->get($entry->handlerClass);

            if (!is_callable($handler)) {
                throw new ListenerException(
                    "Listener '{$entry->handlerClass}' is not callable",
                    $entry->handlerClass,
                    $event::class,
                );
            }

            $handler($event);

            $this->logger->debug('Listener executed', [
                'event' => $event::class,
                'listener' => $entry->handlerClass,
                'async' => false,
            ]);
        } catch (ListenerException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Listener failed', [
                'event' => $event::class,
                'listener' => $entry->handlerClass,
                'error' => $e->getMessage(),
            ]);

            throw new ListenerException(
                "Listener '{$entry->handlerClass}' failed for event '" . $event::class . "'",
                $entry->handlerClass,
                $event::class,
                previous: $e,
            );
        }
    }

    /**
     * Publish an event to the async queue.
     *
     * @param object $event
     * @param ListenerEntry $entry
     *
     * @throws AsyncDispatchException If queue publishing fails
     *
     * @return void
     */
    private function dispatchAsync(object $event, ListenerEntry $entry): void
    {
        try {
            $this->async->publishAsync($event, $entry->handlerClass, $entry->priority);

            $this->logger->debug('Listener queued', [
                'event' => $event::class,
                'listener' => $entry->handlerClass,
                'priority' => $entry->priority,
            ]);
        } catch (AsyncDispatchException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Async dispatch failed', [
                'event' => $event::class,
                'listener' => $entry->handlerClass,
                'error' => $e->getMessage(),
            ]);

            throw new AsyncDispatchException(
                "Failed to queue listener '{$entry->handlerClass}' for event '" . $event::class . "'",
                $entry->handlerClass,
                $event::class,
                previous: $e,
            );
        }
    }
}

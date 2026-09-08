<?php

declare(strict_types=1);

/**
 * PSR-14 event dispatcher with ordering, an async seam, and span
 * observability.
 *
 * Dispatch runs in two phases: every sync listener first — in order, with
 * stoppable-event semantics — then every async listener is handed to the
 * AsyncDispatcherInterface in order. Async listeners never observe or affect
 * propagation: they may run later, elsewhere, or (under the sync fallback)
 * inline after the sync phase — a stopped event still publishes what the
 * ordering already selected, and mutations an async handler makes are not
 * visible to the sync phase that already ran.
 *
 * A sync listener that throws aborts the remaining listeners and surfaces as
 * ListenerException; a publish that fails surfaces as
 * AsyncDispatchException before anything ran. Every dispatch carries a span;
 * every listener execution is an event on it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Contracts\Event\AsyncDispatcherInterface;
use PHPdot\Contracts\Logs\SpanInterface;
use PHPdot\Contracts\Logs\TracerInterface;
use PHPdot\Event\DTO\ListenerEntry;
use PHPdot\Event\Exception\AsyncDispatchException;
use PHPdot\Event\Exception\ListenerException;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Throwable;

#[Singleton]
#[Binds(EventDispatcherInterface::class)]
final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @param ListenerProvider $provider Supplies the ordered listeners for an event
     * @param ContainerInterface $container Resolves handler classes to instances
     * @param AsyncDispatcherInterface $async Receives listeners marked async
     * @param TracerInterface $tracer Observes dispatches and listener executions
     */
    public function __construct(
        private readonly ListenerProvider $provider,
        private readonly ContainerInterface $container,
        private readonly AsyncDispatcherInterface $async,
        private readonly TracerInterface $tracer,
    ) {}

    /**
     * Dispatch an event: all sync listeners in order (stoppable semantics
     * honored), then the async listeners handed to the async backend.
     *
     * @param object $event The event
     *
     * @return object The (possibly modified) event
     */
    public function dispatch(object $event): object
    {
        $span = $this->tracer
            ->channel('event')
            ->span('event.dispatch', 'internal')
            ->setAttribute('event.class', $event::class);

        $stoppedEarly = $event instanceof StoppableEventInterface && $event->isPropagationStopped();
        $asyncEntries = [];

        if (!$stoppedEarly) {
            foreach ($this->provider->entriesForEvent($event) as $entry) {
                if (!$entry->enabled) {
                    continue;
                }

                if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                    break;
                }

                if ($entry->async) {
                    $asyncEntries[] = $entry;

                    continue;
                }

                $this->dispatchSync($event, $entry, $span);
            }
        }

        foreach ($asyncEntries as $entry) {
            $this->dispatchAsync($event, $entry, $span);
        }

        $span->setAttribute('event.stopped', $event instanceof StoppableEventInterface && $event->isPropagationStopped())
            ->end();

        return $event;
    }

    /**
     * Resolve and execute one sync listener.
     *
     * @param object $event The event
     * @param ListenerEntry $entry The listener
     * @param SpanInterface $span The dispatch span
     *
     * @return void
     */
    private function dispatchSync(object $event, ListenerEntry $entry, SpanInterface $span): void
    {
        $span->addEvent('event.listener', [
            'listener' => $entry->handlerClass,
            'async' => false,
            'order' => $entry->order,
        ]);

        try {
            $handler = $this->container->get($entry->handlerClass);

            if (!is_callable($handler)) {
                throw ListenerException::notCallable($entry->handlerClass, $event::class);
            }

            $handler($event);
        } catch (ListenerException $failure) {
            $span->setAttribute('listener.error', $entry->handlerClass);
            throw $failure;
        } catch (Throwable $failure) {
            $span->setAttribute('listener.error', $entry->handlerClass);
            throw ListenerException::failed($entry->handlerClass, $event::class, $failure);
        }
    }

    /**
     * Hand one async listener to the backend.
     *
     * @param object $event The event
     * @param ListenerEntry $entry The listener
     * @param SpanInterface $span The dispatch span
     *
     * @return void
     */
    private function dispatchAsync(object $event, ListenerEntry $entry, SpanInterface $span): void
    {
        $span->addEvent('event.listener', [
            'listener' => $entry->handlerClass,
            'async' => true,
            'order' => $entry->order,
            'priority' => $entry->priority,
        ]);

        try {
            $this->async->publishAsync($event, $entry->handlerClass, $entry->priority);
        } catch (AsyncDispatchException $failure) {
            $span->setAttribute('listener.error', $entry->handlerClass);
            throw $failure;
        } catch (Throwable $failure) {
            $span->setAttribute('listener.error', $entry->handlerClass);
            throw AsyncDispatchException::publishFailed($entry->handlerClass, $event::class, $failure);
        }
    }
}

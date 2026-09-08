<?php

declare(strict_types=1);

/**
 * The no-queue async backend: publish means run, immediately, inline after
 * the sync phase. Priority is meaningless here — there is no queue to order
 * — and a handler failure is a LISTENER failure (it ran), surfacing as
 * ListenerException, never disguised as a queue failure. Swap the
 * AsyncDispatcherInterface binding for a queue backend when timing matters —
 * the application binds what it installed.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Provider;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Contracts\Event\AsyncDispatcherInterface;
use PHPdot\Event\Exception\ListenerException;
use Psr\Container\ContainerInterface;
use Throwable;

#[Singleton]
#[Binds(AsyncDispatcherInterface::class)]
final class SyncOnlyDispatcher implements AsyncDispatcherInterface
{
    /**
     * @param ContainerInterface $container Resolves handler classes to instances
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Execute the handler synchronously instead of queuing it.
     *
     * @param object $event The event
     * @param string $handlerClass The handler to run
     * @param int $priority Ignored — there is no queue to order
     *
     * @return void
     */
    public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
    {
        try {
            $handler = $this->container->get($handlerClass);

            if (!is_callable($handler)) {
                throw ListenerException::notCallable($handlerClass, $event::class);
            }

            $handler($event);
        } catch (ListenerException $failure) {
            throw $failure;
        } catch (Throwable $failure) {
            throw ListenerException::failed($handlerClass, $event::class, $failure);
        }
    }
}

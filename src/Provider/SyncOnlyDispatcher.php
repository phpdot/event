<?php

declare(strict_types=1);

/**
 * Sync-only fallback "async" dispatcher.
 *
 * Runs async handlers synchronously when no message queue is configured.
 * Useful for development, testing, and simple deployments.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Provider;

use PHPdot\Event\Contract\AsyncDispatcherInterface;
use Psr\Container\ContainerInterface;

final class SyncOnlyDispatcher implements AsyncDispatcherInterface
{
    /**
     * A fallback async dispatcher that resolves and runs handlers synchronously.
     *
     * @param ContainerInterface $container Resolves handler classes to instances
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Execute the handler synchronously instead of queuing it.
     */
    public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
    {
        $handler = $this->container->get($handlerClass);

        if (!is_callable($handler)) {
            throw new \RuntimeException("Handler '{$handlerClass}' is not callable");
        }

        $handler($event);
    }
}

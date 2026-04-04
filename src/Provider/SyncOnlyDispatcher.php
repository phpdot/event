<?php

declare(strict_types=1);

namespace PHPdot\Event\Provider;

use PHPdot\Event\Contract\AsyncDispatcherInterface;
use Psr\Container\ContainerInterface;

/**
 * Sync-only fallback "async" dispatcher.
 *
 * Runs async handlers synchronously when no message queue is configured.
 * Useful for development, testing, and simple deployments.
 */
final class SyncOnlyDispatcher implements AsyncDispatcherInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

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

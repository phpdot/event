<?php

declare(strict_types=1);

namespace PHPdot\Event\Contract;

/**
 * Publishes events to a message queue for async handling.
 *
 * Framework implements this using its queue layer (e.g. phpdot/queue).
 */
interface AsyncDispatcherInterface
{
    /**
     * Publish an event to be handled asynchronously by the specified handler.
     *
     * @param object $event The event object
     * @param string $handlerClass The handler class to invoke when consuming
     * @param int $priority Queue priority (0-10, higher = more urgent)
     */
    public function publishAsync(object $event, string $handlerClass, int $priority = 0): void;
}

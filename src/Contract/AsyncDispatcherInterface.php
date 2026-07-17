<?php

declare(strict_types=1);

/**
 * Publishes events to a message queue for async handling.
 *
 * Framework implements this using its queue layer (e.g. phpdot/queue).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Contract;

interface AsyncDispatcherInterface
{
    /**
     * Publish an event to be handled asynchronously by the specified handler.
     *
     * @param object $event The event object
     * @param string $handlerClass The handler class to invoke when consuming
     * @param int $priority Queue priority (0-10, higher = more urgent)
     *
     * @return void
     */
    public function publishAsync(object $event, string $handlerClass, int $priority = 0): void;
}

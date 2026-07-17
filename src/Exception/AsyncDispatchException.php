<?php

declare(strict_types=1);

/**
 * Thrown when publishing an event to the async queue fails.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Exception;

final class AsyncDispatchException extends EventException
{
    /**
     * @param string $message Error message
     * @param string $handlerClass The handler that was being queued
     * @param string $eventClass The event being dispatched
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        private readonly string $handlerClass,
        private readonly string $eventClass,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the handler class that was being queued.
     *
     * @return string
     */
    public function getHandlerClass(): string
    {
        return $this->handlerClass;
    }

    /**
     * Get the event class being dispatched.
     *
     * @return string
     */
    public function getEventClass(): string
    {
        return $this->eventClass;
    }
}

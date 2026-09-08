<?php

declare(strict_types=1);

/**
 * Thrown when publishing an event to the async queue fails.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Exception;

use Throwable;

final class AsyncDispatchException extends EventException
{
    /**
     * @param string $message Error message
     * @param string $handlerClass The handler that was being queued
     * @param string $eventClass The event being dispatched
     * @param int $code Error code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        private readonly string $handlerClass,
        private readonly string $eventClass,
        int $code = 0,
        null|Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * A publish to the async backend that failed before the listener ran.
     *
     * @param string $handlerClass The handler that was being queued
     * @param string $eventClass The event being dispatched
     * @param Throwable $failure What the backend threw
     *
     * @return self
     */
    public static function publishFailed(string $handlerClass, string $eventClass, Throwable $failure): self
    {
        return new self(
            "Failed to queue listener '{$handlerClass}' for event '{$eventClass}'",
            $handlerClass,
            $eventClass,
            previous: $failure,
        );
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

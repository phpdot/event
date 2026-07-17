<?php

declare(strict_types=1);

/**
 * Thrown when a listener fails to resolve or execute.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Exception;

final class ListenerException extends EventException
{
    /**
     * @param string $message Error message
     * @param string $handlerClass The handler that failed
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
     * Get the handler class that failed.
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

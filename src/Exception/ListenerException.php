<?php

declare(strict_types=1);

/**
 * Thrown when a listener fails to resolve or execute.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Exception;

use Throwable;

final class ListenerException extends EventException
{
    /**
     * @param string $message Error message
     * @param string $handlerClass The handler that failed
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
     * A listener class that does not exist or is not invokable.
     *
     * @param string $handlerClass The handler that failed
     * @param string $eventClass The event being dispatched
     *
     * @return self
     */
    public static function notCallable(string $handlerClass, string $eventClass): self
    {
        return new self(
            "Listener '{$handlerClass}' is not callable",
            $handlerClass,
            $eventClass,
        );
    }

    /**
     * A listener that exists but could not be autoloaded.
     *
     * @param string $handlerClass The handler that failed
     * @param string $eventClass The event being dispatched
     *
     * @return self
     */
    public static function unknownHandler(string $handlerClass, string $eventClass): self
    {
        return new self(
            "Listener '{$handlerClass}' does not exist",
            $handlerClass,
            $eventClass,
        );
    }

    /**
     * A listener that ran and threw.
     *
     * @param string $handlerClass The handler that failed
     * @param string $eventClass The event being dispatched
     * @param Throwable $failure What the listener threw
     *
     * @return self
     */
    public static function failed(string $handlerClass, string $eventClass, Throwable $failure): self
    {
        return new self(
            "Listener '{$handlerClass}' failed for event '{$eventClass}'",
            $handlerClass,
            $eventClass,
            previous: $failure,
        );
    }

    /**
     * A handler whose __invoke cannot receive the event it declared —
     * the typed-listener law, enforced at discovery.
     *
     * @param string $handlerClass The listener that declared it
     * @param string $eventClass The declared event
     *
     * @return self
     */
    public static function shapeMismatch(string $handlerClass, string $eventClass): self
    {
        return new self(
            "Listener '{$handlerClass}' declares event '{$eventClass}' but its __invoke cannot receive it — one listener, one event, typed",
            $handlerClass,
            $eventClass,
        );
    }

    /**
     * A declared priority outside the documented 0-10 range.
     *
     * @param string $eventClass The declared event
     * @param string $handlerClass The listener that declared it
     * @param int $priority The out-of-range priority
     *
     * @return self
     */
    public static function invalidPriority(string $eventClass, string $handlerClass, int $priority): self
    {
        return new self(
            "Listener '{$handlerClass}' declares priority {$priority} for event '{$eventClass}' — the range is 0-10",
            $handlerClass,
            $eventClass,
        );
    }

    /**
     * A declared listener event that is not a class.
     *
     * @param string $eventClass The declared event
     * @param string $handlerClass The listener that declared it
     *
     * @return self
     */
    public static function unknownEvent(string $eventClass, string $handlerClass): self
    {
        return new self(
            "Listener '{$handlerClass}' declares event '{$eventClass}' which is not a class",
            $handlerClass,
            $eventClass,
        );
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

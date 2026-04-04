<?php

declare(strict_types=1);

namespace PHPdot\Event\Event;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base class for events that support propagation stopping.
 *
 * Events that don't need stopping don't extend this — they're plain objects.
 */
abstract class StoppableEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    /**
     * Is propagation stopped?
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Stop further listeners from being called.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}

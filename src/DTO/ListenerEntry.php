<?php

declare(strict_types=1);

/**
 * Immutable descriptor for a single event→handler binding.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\DTO;

final readonly class ListenerEntry
{
    /**
     * A resolved listener registration: which handler runs for which event, and how.
     *
     * @param string $eventClass Fully qualified event class name
     * @param string $handlerClass Fully qualified handler class name
     * @param int $order Execution sequence (lower = first)
     * @param bool $async Whether to dispatch via queue
     * @param int $priority Queue priority for async listeners (0-10)
     * @param bool $enabled Can be toggled via admin GUI
     */
    public function __construct(
        public string $eventClass,
        public string $handlerClass,
        public int $order = 0,
        public bool $async = false,
        public int $priority = 0,
        public bool $enabled = true,
    ) {}
}

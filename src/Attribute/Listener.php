<?php

declare(strict_types=1);

/**
 * Marks a class as an event listener.
 *
 * The handler declares what it handles — no central configuration file.
 * Discovery finds all listeners at boot time, caches the mapping,
 * and the runtime dispatcher uses zero-cost in-memory lookups.
 *
 * Repeatable: one handler can listen to multiple events via multiple attributes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Listener
{
    /**
     * Mark a class as a listener for an event, with ordering and async options.
     *
     * @param string $event Event class name (e.g. UserRegistered::class)
     * @param int $order Execution order (lower = first, default 0)
     * @param bool $async Run via queue instead of synchronously
     * @param int $priority Queue priority for async listeners (0-10, higher = more urgent)
     */
    public function __construct(
        public string $event,
        public int $order = 0,
        public bool $async = false,
        public int $priority = 0,
    ) {}
}

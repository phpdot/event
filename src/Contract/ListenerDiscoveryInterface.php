<?php

declare(strict_types=1);

namespace PHPdot\Event\Contract;

use PHPdot\Event\DTO\ListenerEntry;

/**
 * Scans for #[Listener] attributes and returns discovered listener entries.
 *
 * Framework implements this using its attribute scanner (e.g. phpdot/attribute).
 */
interface ListenerDiscoveryInterface
{
    /**
     * Discover all listener entries from the application codebase.
     *
     * @return list<ListenerEntry>
     */
    public function discover(): array;
}

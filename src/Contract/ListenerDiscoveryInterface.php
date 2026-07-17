<?php

declare(strict_types=1);

/**
 * Scans for #[Listener] attributes and returns discovered listener entries.
 *
 * Framework implements this using its attribute scanner (e.g. phpdot/attribute).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Contract;

use PHPdot\Event\DTO\ListenerEntry;

interface ListenerDiscoveryInterface
{
    /**
     * Discover all listener entries from the application codebase.
     *
     * @return list<ListenerEntry>
     */
    public function discover(): array;
}

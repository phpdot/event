<?php

declare(strict_types=1);

/**
 * Persists listener mappings for admin GUI management.
 *
 * Framework implements this using its database layer.
 * Allows enabling/disabling listeners and changing order without code deploy.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Contract;

use PHPdot\Event\DTO\ListenerEntry;

interface ListenerRepositoryInterface
{
    /**
     * Get all stored listener entries.
     *
     * @return list<ListenerEntry>
     */
    public function getAll(): array;

    /**
     * Get listener entries for a specific event.
     *
     * @param string $eventClass
     *
     * @return list<ListenerEntry>
     */
    public function getByEvent(string $eventClass): array;

    /**
     * Save a listener entry (create or update).
     *
     * @param ListenerEntry $entry
     *
     * @return void
     */
    public function save(ListenerEntry $entry): void;

    /**
     * Enable or disable a listener.
     *
     * @param string $handlerClass
     * @param bool $enabled
     * @param string $eventClass
     *
     * @return void
     */
    public function setEnabled(string $eventClass, string $handlerClass, bool $enabled): void;

    /**
     * Update the execution order of a listener.
     *
     * @param int $order
     * @param string $eventClass
     * @param string $handlerClass
     *
     * @return void
     */
    public function setOrder(string $eventClass, string $handlerClass, int $order): void;

    /**
     * Delete a listener mapping.
     *
     * @param string $eventClass
     * @param string $handlerClass
     *
     * @return void
     */
    public function delete(string $eventClass, string $handlerClass): void;

    /**
     * Sync discovered listeners with stored ones.
     *
     * Merges newly discovered entries, preserves existing overrides
     * (enabled/disabled, reordered), removes stale entries.
     *
     * @param list<ListenerEntry> $discovered
     *
     * @return void
     */
    public function sync(array $discovered): void;
}

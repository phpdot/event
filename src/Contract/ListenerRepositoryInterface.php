<?php

declare(strict_types=1);

namespace PHPdot\Event\Contract;

use PHPdot\Event\DTO\ListenerEntry;

/**
 * Persists listener mappings for admin GUI management.
 *
 * Framework implements this using its database layer.
 * Allows enabling/disabling listeners and changing order without code deploy.
 */
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
     * @return list<ListenerEntry>
     */
    public function getByEvent(string $eventClass): array;

    /**
     * Save a listener entry (create or update).
     */
    public function save(ListenerEntry $entry): void;

    /**
     * Enable or disable a listener.
     */
    public function setEnabled(string $eventClass, string $handlerClass, bool $enabled): void;

    /**
     * Update the execution order of a listener.
     */
    public function setOrder(string $eventClass, string $handlerClass, int $order): void;

    /**
     * Delete a listener mapping.
     */
    public function delete(string $eventClass, string $handlerClass): void;

    /**
     * Sync discovered listeners with stored ones.
     *
     * Merges newly discovered entries, preserves existing overrides
     * (enabled/disabled, reordered), removes stale entries.
     *
     * @param list<ListenerEntry> $discovered
     */
    public function sync(array $discovered): void;
}

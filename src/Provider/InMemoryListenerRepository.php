<?php

declare(strict_types=1);

namespace PHPdot\Event\Provider;

use PHPdot\Event\Contract\ListenerRepositoryInterface;
use PHPdot\Event\DTO\ListenerEntry;

/**
 * In-memory listener repository. No database needed.
 *
 * Used when admin GUI management is not required.
 */
final class InMemoryListenerRepository implements ListenerRepositoryInterface
{
    /** @var list<ListenerEntry> */
    private array $entries = [];

    /**
     * @return list<ListenerEntry>
     */
    public function getAll(): array
    {
        return $this->entries;
    }

    /**
     * @return list<ListenerEntry>
     */
    public function getByEvent(string $eventClass): array
    {
        return array_values(
            array_filter($this->entries, static fn (ListenerEntry $e): bool => $e->eventClass === $eventClass),
        );
    }

    public function save(ListenerEntry $entry): void
    {
        // Update if exists, otherwise add
        foreach ($this->entries as $i => $existing) {
            if ($existing->eventClass === $entry->eventClass && $existing->handlerClass === $entry->handlerClass) {
                $this->entries[$i] = $entry;

                return;
            }
        }

        $this->entries[] = $entry;
    }

    public function setEnabled(string $eventClass, string $handlerClass, bool $enabled): void
    {
        foreach ($this->entries as $i => $entry) {
            if ($entry->eventClass === $eventClass && $entry->handlerClass === $handlerClass) {
                $this->entries[$i] = new ListenerEntry(
                    eventClass: $entry->eventClass,
                    handlerClass: $entry->handlerClass,
                    order: $entry->order,
                    async: $entry->async,
                    priority: $entry->priority,
                    enabled: $enabled,
                );

                return;
            }
        }
    }

    public function setOrder(string $eventClass, string $handlerClass, int $order): void
    {
        foreach ($this->entries as $i => $entry) {
            if ($entry->eventClass === $eventClass && $entry->handlerClass === $handlerClass) {
                $this->entries[$i] = new ListenerEntry(
                    eventClass: $entry->eventClass,
                    handlerClass: $entry->handlerClass,
                    order: $order,
                    async: $entry->async,
                    priority: $entry->priority,
                    enabled: $entry->enabled,
                );

                return;
            }
        }
    }

    public function delete(string $eventClass, string $handlerClass): void
    {
        $this->entries = array_values(
            array_filter(
                $this->entries,
                static fn (ListenerEntry $e): bool => !($e->eventClass === $eventClass && $e->handlerClass === $handlerClass),
            ),
        );
    }

    /**
     * @param list<ListenerEntry> $discovered
     */
    public function sync(array $discovered): void
    {
        // Preserve overrides for existing entries, add new ones, remove stale ones
        $merged = [];

        foreach ($discovered as $entry) {
            $existing = $this->findEntry($entry->eventClass, $entry->handlerClass);
            if ($existing !== null) {
                // Preserve admin overrides (enabled, order) from stored entry
                $merged[] = $existing;
            } else {
                $merged[] = $entry;
            }
        }

        $this->entries = $merged;
    }

    private function findEntry(string $eventClass, string $handlerClass): ?ListenerEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->eventClass === $eventClass && $entry->handlerClass === $handlerClass) {
                return $entry;
            }
        }

        return null;
    }
}

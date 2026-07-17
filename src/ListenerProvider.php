<?php

declare(strict_types=1);

/**
 * PSR-14 listener provider with in-memory event→handlers mapping.
 *
 * Supports event class hierarchy: a listener on a parent class
 * will be triggered by events of any subclass.
 *
 * Note: getListenersForEvent() returns ListenerEntry objects (not plain callables)
 * so the paired EventDispatcher can access async/priority/enabled metadata.
 * This provider is designed to be used with PHPdot's EventDispatcher.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event;

use PHPdot\Event\Contract\ListenerRepositoryInterface;
use PHPdot\Event\DTO\ListenerEntry;
use Psr\EventDispatcher\ListenerProviderInterface;

final class ListenerProvider implements ListenerProviderInterface
{
    /**
     * @var array<string, list<ListenerEntry>>
     */
    private array $listeners = [];

    /**
     * @var array<string, list<ListenerEntry>>
     */
    private array $resolvedCache = [];

    /**
     * Get listeners for an event, sorted by order.
     *
     * Returns ListenerEntry objects sorted by order (ascending).
     * Matches the event's exact class and all parent classes/interfaces.
     * Results are cached until listeners change.
     *
     * @return iterable<ListenerEntry>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $class = $event::class;

        if (!isset($this->resolvedCache[$class])) {
            $entries = $this->resolveEntries($class);
            usort($entries, static fn(ListenerEntry $a, ListenerEntry $b): int => $a->order <=> $b->order);
            $this->resolvedCache[$class] = $entries;
        }

        return $this->resolvedCache[$class];
    }

    /**
     * Register a listener manually.
     *
     * @param string $eventClass
     * @param string $handlerClass
     * @param int $order
     * @param bool $async
     * @param int $priority
     *
     * @return void
     */
    public function addListener(
        string $eventClass,
        string $handlerClass,
        int $order = 0,
        bool $async = false,
        int $priority = 0,
    ): void {
        $this->listeners[$eventClass][] = new ListenerEntry(
            eventClass: $eventClass,
            handlerClass: $handlerClass,
            order: $order,
            async: $async,
            priority: $priority,
        );
        $this->resolvedCache = [];
    }

    /**
     * Bulk load from discovery results (boot time).
     *
     * @param list<ListenerEntry> $entries
     *
     * @return void
     */
    public function load(array $entries): void
    {
        foreach ($entries as $entry) {
            $this->listeners[$entry->eventClass][] = $entry;
        }
        $this->resolvedCache = [];
    }

    /**
     * Load from persistent storage, merging with existing listeners.
     *
     * Repository entries override existing entries for the same
     * event+handler pair (e.g. changed order, disabled flag).
     *
     * @param ListenerRepositoryInterface $repository
     *
     * @return void
     */
    public function loadFromRepository(ListenerRepositoryInterface $repository): void
    {
        $stored = $repository->getAll();

        foreach ($stored as $entry) {
            $this->mergeEntry($entry);
        }
        $this->resolvedCache = [];
    }

    /**
     * Get all registered listeners grouped by event class.
     *
     * @return array<string, list<ListenerEntry>>
     */
    public function getAll(): array
    {
        return $this->listeners;
    }

    /**
     * Check if any listeners would be triggered for an event class.
     *
     * Checks the event class hierarchy (parents + interfaces), not just direct registration.
     *
     * @param string $eventClass
     *
     * @return bool
     */
    public function hasListeners(string $eventClass): bool
    {
        return $this->resolveEntries($eventClass) !== [];
    }

    /**
     * Remove all listeners for a specific event class.
     *
     * @param string $eventClass
     *
     * @return void
     */
    public function removeListeners(string $eventClass): void
    {
        unset($this->listeners[$eventClass]);
        $this->resolvedCache = [];
    }

    /**
     * Clear all registered listeners.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->listeners = [];
        $this->resolvedCache = [];
    }

    /**
     * Resolve all matching entries for an event class, including parent classes and interfaces.
     *
     * @param string $eventClass
     *
     * @return list<ListenerEntry>
     */
    private function resolveEntries(string $eventClass): array
    {
        $entries = [];

        if (isset($this->listeners[$eventClass])) {
            $entries = $this->listeners[$eventClass];
        }

        $parents = class_parents($eventClass);
        if ($parents !== false) {
            foreach ($parents as $parent) {
                if (isset($this->listeners[$parent])) {
                    $entries = [...$entries, ...$this->listeners[$parent]];
                }
            }
        }

        $interfaces = class_implements($eventClass);
        if ($interfaces !== false) {
            foreach ($interfaces as $interface) {
                if (isset($this->listeners[$interface])) {
                    $entries = [...$entries, ...$this->listeners[$interface]];
                }
            }
        }

        return $entries;
    }

    /**
     * Merge a repository entry with existing listeners.
     *
     * If an entry with the same event+handler already exists, replace it.
     * Otherwise, add it.
     *
     * @param ListenerEntry $entry
     *
     * @return void
     */
    private function mergeEntry(ListenerEntry $entry): void
    {
        if (!isset($this->listeners[$entry->eventClass])) {
            $this->listeners[$entry->eventClass] = [$entry];

            return;
        }

        foreach ($this->listeners[$entry->eventClass] as $i => $existing) {
            if ($existing->handlerClass === $entry->handlerClass) {
                $this->listeners[$entry->eventClass][$i] = $entry;

                return;
            }
        }

        $this->listeners[$entry->eventClass][] = $entry;
    }
}

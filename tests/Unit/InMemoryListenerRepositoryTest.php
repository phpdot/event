<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Unit;

use PHPdot\Event\DTO\ListenerEntry;
use PHPdot\Event\Provider\InMemoryListenerRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryListenerRepositoryTest extends TestCase
{
    #[Test]
    public function it_starts_empty(): void
    {
        $repo = new InMemoryListenerRepository();

        self::assertSame([], $repo->getAll());
    }

    #[Test]
    public function it_saves_and_retrieves_entry(): void
    {
        $repo = new InMemoryListenerRepository();
        $entry = new ListenerEntry('EventA', 'HandlerA');

        $repo->save($entry);

        self::assertCount(1, $repo->getAll());
        self::assertSame('EventA', $repo->getAll()[0]->eventClass);
    }

    #[Test]
    public function it_updates_existing_entry_on_save(): void
    {
        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry('EventA', 'HandlerA', order: 1));
        $repo->save(new ListenerEntry('EventA', 'HandlerA', order: 5));

        self::assertCount(1, $repo->getAll());
        self::assertSame(5, $repo->getAll()[0]->order);
    }

    #[Test]
    public function it_gets_by_event(): void
    {
        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry('EventA', 'HandlerA'));
        $repo->save(new ListenerEntry('EventA', 'HandlerB'));
        $repo->save(new ListenerEntry('EventB', 'HandlerC'));

        $results = $repo->getByEvent('EventA');

        self::assertCount(2, $results);
    }

    #[Test]
    public function it_returns_empty_for_unknown_event(): void
    {
        $repo = new InMemoryListenerRepository();

        self::assertSame([], $repo->getByEvent('Unknown'));
    }

    #[Test]
    public function it_sets_enabled(): void
    {
        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry('EventA', 'HandlerA', enabled: true));

        $repo->setEnabled('EventA', 'HandlerA', false);

        self::assertFalse($repo->getAll()[0]->enabled);
    }

    #[Test]
    public function it_sets_order(): void
    {
        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry('EventA', 'HandlerA', order: 1));

        $repo->setOrder('EventA', 'HandlerA', 99);

        self::assertSame(99, $repo->getAll()[0]->order);
    }

    #[Test]
    public function it_deletes_entry(): void
    {
        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry('EventA', 'HandlerA'));
        $repo->save(new ListenerEntry('EventA', 'HandlerB'));

        $repo->delete('EventA', 'HandlerA');

        self::assertCount(1, $repo->getAll());
        self::assertSame('HandlerB', $repo->getAll()[0]->handlerClass);
    }

    #[Test]
    public function it_syncs_discovered_entries(): void
    {
        $repo = new InMemoryListenerRepository();

        // Existing with admin overrides
        $repo->save(new ListenerEntry('EventA', 'HandlerA', order: 99, enabled: false));
        $repo->save(new ListenerEntry('EventA', 'HandlerStale', order: 1)); // will be removed

        // Discovery
        $discovered = [
            new ListenerEntry('EventA', 'HandlerA', order: 1),
            new ListenerEntry('EventA', 'HandlerNew', order: 2),
        ];

        $repo->sync($discovered);

        $all = $repo->getAll();
        self::assertCount(2, $all);

        // HandlerA should preserve admin overrides
        $handlerA = $this->findEntry($all, 'HandlerA');
        self::assertNotNull($handlerA);
        self::assertSame(99, $handlerA->order);
        self::assertFalse($handlerA->enabled);

        // HandlerNew should use discovered values
        $handlerNew = $this->findEntry($all, 'HandlerNew');
        self::assertNotNull($handlerNew);
        self::assertSame(2, $handlerNew->order);

        // HandlerStale should be removed (not in discovered)
        $stale = $this->findEntry($all, 'HandlerStale');
        self::assertNull($stale);
    }

    #[Test]
    public function it_ignores_set_enabled_for_missing_entry(): void
    {
        $repo = new InMemoryListenerRepository();
        $repo->setEnabled('Missing', 'Missing', false);

        self::assertSame([], $repo->getAll());
    }

    #[Test]
    public function it_ignores_set_order_for_missing_entry(): void
    {
        $repo = new InMemoryListenerRepository();
        $repo->setOrder('Missing', 'Missing', 5);

        self::assertSame([], $repo->getAll());
    }

    #[Test]
    public function it_preserves_other_fields_on_set_enabled(): void
    {
        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry('E', 'H', order: 5, async: true, priority: 7, enabled: true));

        $repo->setEnabled('E', 'H', false);

        $entry = $repo->getAll()[0];
        self::assertSame(5, $entry->order);
        self::assertTrue($entry->async);
        self::assertSame(7, $entry->priority);
        self::assertFalse($entry->enabled);
    }

    #[Test]
    public function it_preserves_other_fields_on_set_order(): void
    {
        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry('E', 'H', order: 1, async: true, priority: 3, enabled: false));

        $repo->setOrder('E', 'H', 99);

        $entry = $repo->getAll()[0];
        self::assertSame(99, $entry->order);
        self::assertTrue($entry->async);
        self::assertSame(3, $entry->priority);
        self::assertFalse($entry->enabled);
    }

    /**
     * @param list<ListenerEntry> $entries
     */
    private function findEntry(array $entries, string $handlerClass): ?ListenerEntry
    {
        foreach ($entries as $entry) {
            if ($entry->handlerClass === $handlerClass) {
                return $entry;
            }
        }

        return null;
    }
}

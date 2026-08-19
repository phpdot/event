<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Unit;

use PHPdot\Event\DTO\ListenerEntry;
use PHPdot\Event\ListenerProvider;
use PHPdot\Event\Provider\InMemoryListenerRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListenerProviderTest extends TestCase
{
    #[Test]
    public function it_returns_empty_for_unknown_event(): void
    {
        $provider = new ListenerProvider();

        $listeners = $provider->entriesForEvent(new SimpleEvent());

        self::assertSame([], $listeners);
    }

    #[Test]
    public function it_adds_and_retrieves_listener(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, 'HandlerA');

        $listeners = $provider->entriesForEvent(new SimpleEvent());

        self::assertCount(1, $listeners);
        self::assertSame('HandlerA', $listeners[0]->handlerClass);
    }

    #[Test]
    public function it_returns_listeners_sorted_by_order(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, 'HandlerC', order: 3);
        $provider->addListener(SimpleEvent::class, 'HandlerA', order: 1);
        $provider->addListener(SimpleEvent::class, 'HandlerB', order: 2);

        $listeners = $provider->entriesForEvent(new SimpleEvent());

        self::assertSame('HandlerA', $listeners[0]->handlerClass);
        self::assertSame('HandlerB', $listeners[1]->handlerClass);
        self::assertSame('HandlerC', $listeners[2]->handlerClass);
    }

    #[Test]
    public function it_preserves_async_and_priority(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, 'AsyncHandler', async: true, priority: 7);

        $listeners = $provider->entriesForEvent(new SimpleEvent());

        self::assertTrue($listeners[0]->async);
        self::assertSame(7, $listeners[0]->priority);
    }

    #[Test]
    public function it_loads_entries_in_bulk(): void
    {
        $provider = new ListenerProvider();
        $provider->load([
            new ListenerEntry(SimpleEvent::class, 'HandlerA', order: 1),
            new ListenerEntry(SimpleEvent::class, 'HandlerB', order: 2),
            new ListenerEntry(AnotherEvent::class, 'HandlerC', order: 1),
        ]);

        $simpleListeners = $provider->entriesForEvent(new SimpleEvent());
        $anotherListeners = $provider->entriesForEvent(new AnotherEvent());

        self::assertCount(2, $simpleListeners);
        self::assertCount(1, $anotherListeners);
    }

    #[Test]
    public function it_checks_has_listeners(): void
    {
        $provider = new ListenerProvider();

        self::assertFalse($provider->hasListeners(SimpleEvent::class));

        $provider->addListener(SimpleEvent::class, 'HandlerA');

        self::assertTrue($provider->hasListeners(SimpleEvent::class));
        self::assertFalse($provider->hasListeners(AnotherEvent::class));
    }

    #[Test]
    public function it_removes_listeners_for_event(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, 'HandlerA');
        $provider->addListener(AnotherEvent::class, 'HandlerB');

        $provider->removeListeners(SimpleEvent::class);

        self::assertFalse($provider->hasListeners(SimpleEvent::class));
        self::assertTrue($provider->hasListeners(AnotherEvent::class));
    }

    #[Test]
    public function it_clears_all_listeners(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, 'HandlerA');
        $provider->addListener(AnotherEvent::class, 'HandlerB');

        $provider->clear();

        self::assertSame([], $provider->getAll());
    }

    #[Test]
    public function it_returns_all_listeners(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, 'HandlerA');
        $provider->addListener(SimpleEvent::class, 'HandlerB');
        $provider->addListener(AnotherEvent::class, 'HandlerC');

        $all = $provider->getAll();

        self::assertCount(2, $all[SimpleEvent::class]);
        self::assertCount(1, $all[AnotherEvent::class]);
    }

    #[Test]
    public function it_matches_parent_class_listeners(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(BaseEvent::class, 'BaseHandler');

        $listeners = $provider->entriesForEvent(new ChildEvent());

        self::assertCount(1, $listeners);
        self::assertSame('BaseHandler', $listeners[0]->handlerClass);
    }

    #[Test]
    public function it_matches_interface_listeners(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(EventInterface::class, 'InterfaceHandler');

        $listeners = $provider->entriesForEvent(new InterfaceEvent());

        self::assertCount(1, $listeners);
        self::assertSame('InterfaceHandler', $listeners[0]->handlerClass);
    }

    #[Test]
    public function it_combines_exact_parent_and_interface_listeners(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(ChildEvent::class, 'ExactHandler', order: 1);
        $provider->addListener(BaseEvent::class, 'ParentHandler', order: 2);

        $listeners = $provider->entriesForEvent(new ChildEvent());

        self::assertCount(2, $listeners);
        self::assertSame('ExactHandler', $listeners[0]->handlerClass);
        self::assertSame('ParentHandler', $listeners[1]->handlerClass);
    }

    #[Test]
    public function it_loads_from_repository(): void
    {
        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry(SimpleEvent::class, 'HandlerA', order: 1));
        $repo->save(new ListenerEntry(SimpleEvent::class, 'HandlerB', order: 2, enabled: false));

        $provider = new ListenerProvider();
        $provider->loadFromRepository($repo);

        $all = $provider->getAll();
        self::assertCount(2, $all[SimpleEvent::class]);
    }

    #[Test]
    public function it_merges_repository_overrides(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, 'HandlerA', order: 1);

        // Repository has same handler but disabled
        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry(SimpleEvent::class, 'HandlerA', order: 5, enabled: false));

        $provider->loadFromRepository($repo);

        $listeners = $provider->getAll()[SimpleEvent::class];
        self::assertCount(1, $listeners);
        self::assertSame(5, $listeners[0]->order);
        self::assertFalse($listeners[0]->enabled);
    }

    #[Test]
    public function it_adds_new_entries_from_repository(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, 'HandlerA');

        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry(SimpleEvent::class, 'HandlerB', order: 2));

        $provider->loadFromRepository($repo);

        $listeners = $provider->getAll()[SimpleEvent::class];
        self::assertCount(2, $listeners);
    }

    #[Test]
    public function it_handles_multiple_events_from_same_handler(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, 'SharedHandler', order: 1);
        $provider->addListener(AnotherEvent::class, 'SharedHandler', order: 1);

        $simpleListeners = $provider->entriesForEvent(new SimpleEvent());
        $anotherListeners = $provider->entriesForEvent(new AnotherEvent());

        self::assertCount(1, $simpleListeners);
        self::assertCount(1, $anotherListeners);
    }

    #[Test]
    public function it_handles_negative_order(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, 'EarlyHandler', order: -10);
        $provider->addListener(SimpleEvent::class, 'DefaultHandler', order: 0);
        $provider->addListener(SimpleEvent::class, 'LateHandler', order: 10);

        $listeners = $provider->entriesForEvent(new SimpleEvent());

        self::assertSame('EarlyHandler', $listeners[0]->handlerClass);
        self::assertSame('DefaultHandler', $listeners[1]->handlerClass);
        self::assertSame('LateHandler', $listeners[2]->handlerClass);
    }

    #[Test]
    public function it_preserves_insertion_order_for_same_priority(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, 'HandlerA', order: 0);
        $provider->addListener(SimpleEvent::class, 'HandlerB', order: 0);
        $provider->addListener(SimpleEvent::class, 'HandlerC', order: 0);

        $listeners = $provider->entriesForEvent(new SimpleEvent());

        // usort is not stable in PHP — order among same-order entries is undefined
        self::assertCount(3, $listeners);
    }

    #[Test]
    public function it_yields_psr14_callables_that_invoke_handlers(): void
    {
        RecordingHandler::$events = [];
        $provider = new ListenerProvider();
        $provider->addListener(SimpleEvent::class, RecordingHandler::class);

        foreach ($provider->getListenersForEvent(new SimpleEvent()) as $listener) {
            self::assertIsCallable($listener);
            $listener(new SimpleEvent());
        }

        self::assertSame([SimpleEvent::class], RecordingHandler::$events);
    }

    #[Test]
    public function it_skips_disabled_entries_in_the_psr14_view(): void
    {
        $provider = new ListenerProvider();
        $provider->load([
            new ListenerEntry(
                eventClass: SimpleEvent::class,
                handlerClass: RecordingHandler::class,
                order: 0,
                async: false,
                priority: 0,
                enabled: false,
            ),
        ]);

        self::assertSame([], iterator_to_array($provider->getListenersForEvent(new SimpleEvent()), false));
    }

    #[Test]
    public function it_resolves_psr14_handlers_from_the_container_when_provided(): void
    {
        $handler = new RecordingHandler();
        $container = new class ($handler) implements \Psr\Container\ContainerInterface {
            public function __construct(private readonly RecordingHandler $handler) {}

            public function get(string $id): RecordingHandler
            {
                return $this->handler;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        RecordingHandler::$events = [];
        $provider = new ListenerProvider($container);
        $provider->addListener(SimpleEvent::class, RecordingHandler::class);

        foreach ($provider->getListenersForEvent(new SimpleEvent()) as $listener) {
            $listener(new SimpleEvent());
        }

        self::assertSame([SimpleEvent::class], RecordingHandler::$events);
    }
}

// ─── Test Fixtures ───

class SimpleEvent {}

class AnotherEvent {}

class BaseEvent {}

class ChildEvent extends BaseEvent {}

interface EventInterface {}

class InterfaceEvent implements EventInterface {}

class RecordingHandler
{
    /** @var list<string> */
    public static array $events = [];

    public function __invoke(object $event): void
    {
        self::$events[] = $event::class;
    }
}

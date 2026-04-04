<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Integration;

use PHPdot\Event\Contract\AsyncDispatcherInterface;
use PHPdot\Event\DTO\ListenerEntry;
use PHPdot\Event\EventDispatcher;
use PHPdot\Event\ListenerProvider;
use PHPdot\Event\Provider\InMemoryListenerRepository;
use PHPdot\Event\Provider\SyncOnlyDispatcher;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DispatchFlowTest extends TestCase
{
    #[Test]
    public function it_implements_psr14(): void
    {
        $dispatcher = $this->createDispatcher(new ListenerProvider(), []);

        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    }

    #[Test]
    public function it_dispatches_full_flow_with_multiple_listeners(): void
    {
        $log = [];

        $provider = new ListenerProvider();
        $provider->load([
            new ListenerEntry(OrderPlaced::class, 'chargePayment', order: 1),
            new ListenerEntry(OrderPlaced::class, 'reserveInventory', order: 2),
            new ListenerEntry(OrderPlaced::class, 'sendConfirmation', order: 3),
        ]);

        $services = [
            'chargePayment' => new class ($log) {
                /** @param list<string> $log */
                public function __construct(private array &$log)
                {
                }

                public function __invoke(OrderPlaced $event): void
                {
                    $this->log[] = "charged:{$event->total}";
                }
            },
            'reserveInventory' => new class ($log) {
                /** @param list<string> $log */
                public function __construct(private array &$log)
                {
                }

                public function __invoke(OrderPlaced $event): void
                {
                    $this->log[] = "reserved:{$event->orderId}";
                }
            },
            'sendConfirmation' => new class ($log) {
                /** @param list<string> $log */
                public function __construct(private array &$log)
                {
                }

                public function __invoke(OrderPlaced $event): void
                {
                    $this->log[] = "emailed:{$event->userId}";
                }
            },
        ];

        $dispatcher = $this->createDispatcher($provider, $services);

        $event = new OrderPlaced(orderId: 42, userId: 1, total: 99.99);
        $returned = $dispatcher->dispatch($event);

        self::assertSame($event, $returned);
        self::assertSame([
            'charged:99.99',
            'reserved:42',
            'emailed:1',
        ], $log);
    }

    #[Test]
    public function it_dispatches_with_sync_only_fallback(): void
    {
        $called = false;

        $provider = new ListenerProvider();
        $provider->addListener(OrderPlaced::class, 'asyncHandler', async: true, priority: 5);

        $container = $this->createContainer([
            'asyncHandler' => new class ($called) {
                public function __construct(private bool &$called)
                {
                }

                public function __invoke(OrderPlaced $event): void
                {
                    $this->called = true;
                }
            },
        ]);

        // SyncOnlyDispatcher runs "async" handlers synchronously
        $async = new SyncOnlyDispatcher($container);
        $dispatcher = new EventDispatcher($provider, $container, $async, new NullLogger());

        $dispatcher->dispatch(new OrderPlaced(1, 1, 10.0));

        self::assertTrue($called);
    }

    #[Test]
    public function it_loads_from_repository_and_dispatches(): void
    {
        $called = false;

        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry(OrderPlaced::class, 'handler', order: 1));

        $provider = new ListenerProvider();
        $provider->loadFromRepository($repo);

        $services = [
            'handler' => new class ($called) {
                public function __construct(private bool &$called)
                {
                }

                public function __invoke(object $event): void
                {
                    $this->called = true;
                }
            },
        ];

        $dispatcher = $this->createDispatcher($provider, $services);
        $dispatcher->dispatch(new OrderPlaced(1, 1, 10.0));

        self::assertTrue($called);
    }

    #[Test]
    public function it_respects_disabled_from_repository(): void
    {
        $called = false;

        $provider = new ListenerProvider();
        $provider->addListener(OrderPlaced::class, 'handler', order: 1);

        // Repository disables the handler
        $repo = new InMemoryListenerRepository();
        $repo->save(new ListenerEntry(OrderPlaced::class, 'handler', order: 1, enabled: false));
        $provider->loadFromRepository($repo);

        $services = [
            'handler' => new class ($called) {
                public function __construct(private bool &$called)
                {
                }

                public function __invoke(object $event): void
                {
                    $this->called = true;
                }
            },
        ];

        $dispatcher = $this->createDispatcher($provider, $services);
        $dispatcher->dispatch(new OrderPlaced(1, 1, 10.0));

        self::assertFalse($called);
    }

    #[Test]
    public function it_dispatches_event_to_parent_class_listener(): void
    {
        $called = false;

        $provider = new ListenerProvider();
        $provider->addListener(BaseOrderEvent::class, 'handler');

        $services = [
            'handler' => new class ($called) {
                public function __construct(private bool &$called)
                {
                }

                public function __invoke(object $event): void
                {
                    $this->called = true;
                }
            },
        ];

        $dispatcher = $this->createDispatcher($provider, $services);
        $dispatcher->dispatch(new SpecialOrderPlaced(1, 1, 10.0, 'PROMO'));

        self::assertTrue($called);
    }

    #[Test]
    public function it_handles_event_with_no_listeners(): void
    {
        $provider = new ListenerProvider();
        $dispatcher = $this->createDispatcher($provider, []);

        $event = new OrderPlaced(1, 1, 10.0);
        $result = $dispatcher->dispatch($event);

        self::assertSame($event, $result);
    }

    #[Test]
    public function it_supports_mutable_events(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(MutableOrderEvent::class, 'addTax', order: 1);
        $provider->addListener(MutableOrderEvent::class, 'addDiscount', order: 2);

        $services = [
            'addTax' => new class {
                public function __invoke(MutableOrderEvent $event): void
                {
                    $event->total += $event->total * 0.1; // 10% tax
                }
            },
            'addDiscount' => new class {
                public function __invoke(MutableOrderEvent $event): void
                {
                    $event->total -= 5.0; // $5 discount
                }
            },
        ];

        $dispatcher = $this->createDispatcher($provider, $services);

        $event = new MutableOrderEvent(100.0);
        $dispatcher->dispatch($event);

        // 100 + 10% = 110 - 5 = 105
        self::assertEqualsWithDelta(105.0, $event->total, 0.01);
    }

    // ─── Helpers ───

    /**
     * @param array<string, object> $services
     */
    private function createDispatcher(ListenerProvider $provider, array $services): EventDispatcher
    {
        $container = $this->createContainer($services);
        $async = new class implements AsyncDispatcherInterface {
            public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
            {
            }
        };

        return new EventDispatcher($provider, $container, $async, new NullLogger());
    }

    /**
     * @param array<string, mixed> $services
     */
    private function createContainer(array $services): ContainerInterface
    {
        return new class ($services) implements ContainerInterface {
            /** @param array<string, mixed> $services */
            public function __construct(private readonly array $services)
            {
            }

            public function get(string $id): mixed
            {
                return $this->services[$id] ?? throw new \RuntimeException("Not found: {$id}");
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }
}

// ─── Test Fixtures ───

final readonly class OrderPlaced
{
    public function __construct(
        public int $orderId,
        public int $userId,
        public float $total,
    ) {
    }
}

class BaseOrderEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly float $total,
    ) {
    }
}

final class SpecialOrderPlaced extends BaseOrderEvent
{
    public function __construct(
        int $orderId,
        int $userId,
        float $total,
        public readonly string $promoCode,
    ) {
        parent::__construct($orderId, $userId, $total);
    }
}

final class MutableOrderEvent
{
    public function __construct(
        public float $total,
    ) {
    }
}

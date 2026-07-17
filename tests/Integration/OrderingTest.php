<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Integration;

use PHPdot\Event\Contract\AsyncDispatcherInterface;
use PHPdot\Event\DTO\ListenerEntry;
use PHPdot\Event\EventDispatcher;
use PHPdot\Event\ListenerProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderingTest extends TestCase
{
    #[Test]
    public function it_executes_in_ascending_order(): void
    {
        $order = [];

        $provider = new ListenerProvider();
        $provider->load([
            new ListenerEntry(TaskEvent::class, 'step3', order: 30),
            new ListenerEntry(TaskEvent::class, 'step1', order: 10),
            new ListenerEntry(TaskEvent::class, 'step2', order: 20),
        ]);

        $services = $this->createHandlers($order, ['step1', 'step2', 'step3']);
        $dispatcher = $this->createDispatcher($provider, $services);

        $dispatcher->dispatch(new TaskEvent());

        self::assertSame(['step1', 'step2', 'step3'], $order);
    }

    #[Test]
    public function it_handles_negative_order(): void
    {
        $order = [];

        $provider = new ListenerProvider();
        $provider->load([
            new ListenerEntry(TaskEvent::class, 'normal', order: 0),
            new ListenerEntry(TaskEvent::class, 'early', order: -100),
            new ListenerEntry(TaskEvent::class, 'late', order: 100),
        ]);

        $services = $this->createHandlers($order, ['early', 'normal', 'late']);
        $dispatcher = $this->createDispatcher($provider, $services);

        $dispatcher->dispatch(new TaskEvent());

        self::assertSame(['early', 'normal', 'late'], $order);
    }

    #[Test]
    public function it_handles_large_number_of_listeners(): void
    {
        $order = [];
        $entries = [];
        $services = [];

        for ($i = 20; $i >= 1; $i--) {
            $name = "handler_{$i}";
            $entries[] = new ListenerEntry(TaskEvent::class, $name, order: $i);
            $capturedI = $i;
            $services[$name] = new class ($order, $capturedI) {
                /** @param list<int> $order */
                public function __construct(private array &$order, private readonly int $i)
                {
                }

                public function __invoke(object $event): void
                {
                    $this->order[] = $this->i;
                }
            };
        }

        $provider = new ListenerProvider();
        $provider->load($entries);

        $dispatcher = $this->createDispatcher($provider, $services);
        $dispatcher->dispatch(new TaskEvent());

        self::assertSame(range(1, 20), $order);
    }

    #[Test]
    public function it_orders_mixed_sync_and_async_by_order_field(): void
    {
        $executionOrder = [];

        $async = new class ($executionOrder) implements AsyncDispatcherInterface {
            /** @param list<string> $order */
            public function __construct(private array &$order)
            {
            }

            public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
            {
                $this->order[] = "async:{$handlerClass}";
            }
        };

        $provider = new ListenerProvider();
        $provider->load([
            new ListenerEntry(TaskEvent::class, 'syncLate', order: 3),
            new ListenerEntry(TaskEvent::class, 'asyncFirst', order: 1, async: true),
            new ListenerEntry(TaskEvent::class, 'syncMiddle', order: 2),
        ]);

        $services = [
            'syncLate' => new class ($executionOrder) {
                /** @param list<string> $order */
                public function __construct(private array &$order)
                {
                }

                public function __invoke(object $event): void
                {
                    $this->order[] = 'sync:syncLate';
                }
            },
            'syncMiddle' => new class ($executionOrder) {
                /** @param list<string> $order */
                public function __construct(private array &$order)
                {
                }

                public function __invoke(object $event): void
                {
                    $this->order[] = 'sync:syncMiddle';
                }
            },
        ];

        $container = $this->createContainer($services);
        $dispatcher = new EventDispatcher($provider, $container, $async, new NullLogger());

        $dispatcher->dispatch(new TaskEvent());

        self::assertSame([
            'async:asyncFirst',
            'sync:syncMiddle',
            'sync:syncLate',
        ], $executionOrder);
    }

    // ─── Helpers ───

    /**
     * @param list<string> $order
     * @param list<string> $names
     * @return array<string, object>
     */
    private function createHandlers(array &$order, array $names): array
    {
        $services = [];
        foreach ($names as $name) {
            $services[$name] = new class ($order, $name) {
                /** @param list<string> $order */
                public function __construct(private array &$order, private readonly string $name)
                {
                }

                public function __invoke(object $event): void
                {
                    $this->order[] = $this->name;
                }
            };
        }

        return $services;
    }

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

final class TaskEvent
{
}

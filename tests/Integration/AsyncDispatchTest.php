<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Integration;

use PHPdot\Event\Contract\AsyncDispatcherInterface;
use PHPdot\Event\DTO\ListenerEntry;
use PHPdot\Event\EventDispatcher;
use PHPdot\Event\Exception\AsyncDispatchException;
use PHPdot\Event\ListenerProvider;
use PHPdot\Event\Provider\SyncOnlyDispatcher;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AsyncDispatchTest extends TestCase
{
    #[Test]
    public function it_publishes_async_listener_with_priority(): void
    {
        $published = [];

        $async = new class ($published) implements AsyncDispatcherInterface {
            /** @param list<array{event: string, handler: string, priority: int}> $published */
            public function __construct(private array &$published)
            {
            }

            public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
            {
                $this->published[] = [
                    'event' => $event::class,
                    'handler' => $handlerClass,
                    'priority' => $priority,
                ];
            }
        };

        $provider = new ListenerProvider();
        $provider->load([
            new ListenerEntry(EmailEvent::class, 'highPriority', async: true, priority: 10, order: 1),
            new ListenerEntry(EmailEvent::class, 'lowPriority', async: true, priority: 1, order: 2),
        ]);

        $container = $this->createEmptyContainer();
        $dispatcher = new EventDispatcher($provider, $container, $async, new NullLogger());

        $dispatcher->dispatch(new EmailEvent('omar@test.com'));

        self::assertCount(2, $published);
        self::assertSame(10, $published[0]['priority']);
        self::assertSame('highPriority', $published[0]['handler']);
        self::assertSame(1, $published[1]['priority']);
        self::assertSame('lowPriority', $published[1]['handler']);
    }

    #[Test]
    public function it_passes_event_object_to_async_dispatcher(): void
    {
        $receivedEvent = null;

        $async = new class ($receivedEvent) implements AsyncDispatcherInterface {
            public function __construct(private ?object &$event)
            {
            }

            public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
            {
                $this->event = $event;
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(EmailEvent::class, 'handler', async: true);

        $dispatcher = new EventDispatcher($provider, $this->createEmptyContainer(), $async, new NullLogger());

        $event = new EmailEvent('omar@test.com');
        $dispatcher->dispatch($event);

        self::assertNotNull($receivedEvent);
        self::assertInstanceOf(EmailEvent::class, $receivedEvent);
        self::assertSame('omar@test.com', $receivedEvent->email);
    }

    #[Test]
    public function it_throws_async_dispatch_exception_on_failure(): void
    {
        $async = new class implements AsyncDispatcherInterface {
            public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
            {
                throw new \RuntimeException('Connection refused');
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(EmailEvent::class, 'handler', async: true);

        $dispatcher = new EventDispatcher($provider, $this->createEmptyContainer(), $async, new NullLogger());

        try {
            $dispatcher->dispatch(new EmailEvent('test@test.com'));
            self::fail('Expected AsyncDispatchException');
        } catch (AsyncDispatchException $e) {
            self::assertSame('handler', $e->getHandlerClass());
            self::assertSame(EmailEvent::class, $e->getEventClass());
            self::assertStringContainsString('Connection refused', $e->getPrevious()?->getMessage() ?? '');
        }
    }

    #[Test]
    public function it_does_not_call_sync_handler_for_async_entry(): void
    {
        $syncCalled = false;

        $async = new class implements AsyncDispatcherInterface {
            public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
            {
                // Published to queue — handler NOT called here
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(EmailEvent::class, 'asyncOnly', async: true);

        $services = [
            'asyncOnly' => new class ($syncCalled) {
                public function __construct(private bool &$called)
                {
                }

                public function __invoke(object $event): void
                {
                    $this->called = true;
                }
            },
        ];

        $container = $this->createContainer($services);
        $dispatcher = new EventDispatcher($provider, $container, $async, new NullLogger());

        $dispatcher->dispatch(new EmailEvent('test@test.com'));

        // Handler should NOT be called — it was queued, not executed
        self::assertFalse($syncCalled);
    }

    #[Test]
    public function sync_only_dispatcher_runs_all_handlers(): void
    {
        $results = [];

        $container = $this->createContainer([
            'handlerA' => new class ($results) {
                /** @param list<string> $results */
                public function __construct(private array &$results)
                {
                }

                public function __invoke(EmailEvent $event): void
                {
                    $this->results[] = "A:{$event->email}";
                }
            },
            'handlerB' => new class ($results) {
                /** @param list<string> $results */
                public function __construct(private array &$results)
                {
                }

                public function __invoke(EmailEvent $event): void
                {
                    $this->results[] = "B:{$event->email}";
                }
            },
        ]);

        $async = new SyncOnlyDispatcher($container);

        $provider = new ListenerProvider();
        $provider->load([
            new ListenerEntry(EmailEvent::class, 'handlerA', async: true, priority: 5, order: 1),
            new ListenerEntry(EmailEvent::class, 'handlerB', async: true, priority: 1, order: 2),
        ]);

        $dispatcher = new EventDispatcher($provider, $container, $async, new NullLogger());
        $dispatcher->dispatch(new EmailEvent('omar@test.com'));

        self::assertSame(['A:omar@test.com', 'B:omar@test.com'], $results);
    }

    // ─── Helpers ───

    private function createEmptyContainer(): ContainerInterface
    {
        return $this->createContainer([]);
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

// ─── Fixtures ───

final readonly class EmailEvent
{
    public function __construct(
        public string $email,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Unit;

use PHPdot\Event\Contract\AsyncDispatcherInterface;
use PHPdot\Event\DTO\ListenerEntry;
use PHPdot\Event\Event\StoppableEvent;
use PHPdot\Event\EventDispatcher;
use PHPdot\Event\Exception\AsyncDispatchException;
use PHPdot\Event\Exception\ListenerException;
use PHPdot\Event\ListenerProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    #[Test]
    public function it_dispatches_to_sync_listener(): void
    {
        $called = false;
        $handler = new class ($called) {
            public function __construct(private bool &$called)
            {
            }

            public function __invoke(object $event): void
            {
                $this->called = true;
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, 'handler');

        $container = $this->createContainer(['handler' => $handler]);
        $dispatcher = new EventDispatcher($provider, $container, $this->createNullAsync(), new NullLogger());

        $dispatcher->dispatch(new TestEvent('hello'));

        self::assertTrue($called);
    }

    #[Test]
    public function it_returns_the_event_object(): void
    {
        $provider = new ListenerProvider();
        $dispatcher = new EventDispatcher($provider, $this->createEmptyContainer(), $this->createNullAsync(), new NullLogger());

        $event = new TestEvent('original');
        $returned = $dispatcher->dispatch($event);

        self::assertSame($event, $returned);
    }

    #[Test]
    public function it_dispatches_in_order(): void
    {
        $order = [];

        $handlerA = new class ($order) {
            /** @param list<string> $order */
            public function __construct(private array &$order)
            {
            }

            public function __invoke(object $event): void
            {
                $this->order[] = 'A';
            }
        };

        $handlerB = new class ($order) {
            /** @param list<string> $order */
            public function __construct(private array &$order)
            {
            }

            public function __invoke(object $event): void
            {
                $this->order[] = 'B';
            }
        };

        $handlerC = new class ($order) {
            /** @param list<string> $order */
            public function __construct(private array &$order)
            {
            }

            public function __invoke(object $event): void
            {
                $this->order[] = 'C';
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, 'handlerC', order: 3);
        $provider->addListener(TestEvent::class, 'handlerA', order: 1);
        $provider->addListener(TestEvent::class, 'handlerB', order: 2);

        $container = $this->createContainer([
            'handlerA' => $handlerA,
            'handlerB' => $handlerB,
            'handlerC' => $handlerC,
        ]);

        $dispatcher = new EventDispatcher($provider, $container, $this->createNullAsync(), new NullLogger());
        $dispatcher->dispatch(new TestEvent('test'));

        self::assertSame(['A', 'B', 'C'], $order);
    }

    #[Test]
    public function it_dispatches_async_listeners_to_queue(): void
    {
        $published = [];
        $async = new class ($published) implements AsyncDispatcherInterface {
            /** @param list<array{event: object, handler: string, priority: int}> $published */
            public function __construct(private array &$published)
            {
            }

            public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
            {
                $this->published[] = ['event' => $event, 'handler' => $handlerClass, 'priority' => $priority];
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, 'AsyncHandler', async: true, priority: 5);

        $dispatcher = new EventDispatcher($provider, $this->createEmptyContainer(), $async, new NullLogger());
        $dispatcher->dispatch(new TestEvent('async'));

        self::assertCount(1, $published);
        self::assertSame('AsyncHandler', $published[0]['handler']);
        self::assertSame(5, $published[0]['priority']);
    }

    #[Test]
    public function it_mixes_sync_and_async_listeners(): void
    {
        $syncCalled = false;
        $asyncPublished = false;

        $syncHandler = new class ($syncCalled) {
            public function __construct(private bool &$called)
            {
            }

            public function __invoke(object $event): void
            {
                $this->called = true;
            }
        };

        $async = new class ($asyncPublished) implements AsyncDispatcherInterface {
            public function __construct(private bool &$published)
            {
            }

            public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
            {
                $this->published = true;
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, 'sync', order: 1);
        $provider->addListener(TestEvent::class, 'async', order: 2, async: true);

        $container = $this->createContainer(['sync' => $syncHandler]);
        $dispatcher = new EventDispatcher($provider, $container, $async, new NullLogger());
        $dispatcher->dispatch(new TestEvent('mixed'));

        self::assertTrue($syncCalled);
        self::assertTrue($asyncPublished);
    }

    #[Test]
    public function it_stops_propagation(): void
    {
        $handlerACalled = false;
        $handlerBCalled = false;

        $handlerA = new class ($handlerACalled) {
            public function __construct(private bool &$called)
            {
            }

            public function __invoke(TestStoppableEvent $event): void
            {
                $this->called = true;
                $event->stopPropagation();
            }
        };

        $handlerB = new class ($handlerBCalled) {
            public function __construct(private bool &$called)
            {
            }

            public function __invoke(object $event): void
            {
                $this->called = true;
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(TestStoppableEvent::class, 'handlerA', order: 1);
        $provider->addListener(TestStoppableEvent::class, 'handlerB', order: 2);

        $container = $this->createContainer(['handlerA' => $handlerA, 'handlerB' => $handlerB]);
        $dispatcher = new EventDispatcher($provider, $container, $this->createNullAsync(), new NullLogger());

        $event = new TestStoppableEvent();
        $dispatcher->dispatch($event);

        self::assertTrue($handlerACalled);
        self::assertFalse($handlerBCalled);
        self::assertTrue($event->isPropagationStopped());
    }

    #[Test]
    public function it_skips_already_stopped_event(): void
    {
        $called = false;
        $handler = new class ($called) {
            public function __construct(private bool &$called)
            {
            }

            public function __invoke(object $event): void
            {
                $this->called = true;
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(TestStoppableEvent::class, 'handler');

        $container = $this->createContainer(['handler' => $handler]);
        $dispatcher = new EventDispatcher($provider, $container, $this->createNullAsync(), new NullLogger());

        $event = new TestStoppableEvent();
        $event->stopPropagation();
        $dispatcher->dispatch($event);

        self::assertFalse($called);
    }

    #[Test]
    public function it_skips_disabled_listeners(): void
    {
        $called = false;
        $handler = new class ($called) {
            public function __construct(private bool &$called)
            {
            }

            public function __invoke(object $event): void
            {
                $this->called = true;
            }
        };

        $provider = new ListenerProvider();
        $provider->load([
            new ListenerEntry(TestEvent::class, 'handler', enabled: false),
        ]);

        $container = $this->createContainer(['handler' => $handler]);
        $dispatcher = new EventDispatcher($provider, $container, $this->createNullAsync(), new NullLogger());

        $dispatcher->dispatch(new TestEvent('test'));

        self::assertFalse($called);
    }

    #[Test]
    public function it_throws_listener_exception_on_handler_failure(): void
    {
        $handler = new class {
            public function __invoke(object $event): void
            {
                throw new \RuntimeException('Handler broke');
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, 'broken');

        $container = $this->createContainer(['broken' => $handler]);
        $dispatcher = new EventDispatcher($provider, $container, $this->createNullAsync(), new NullLogger());

        try {
            $dispatcher->dispatch(new TestEvent('fail'));
            self::fail('Expected ListenerException');
        } catch (ListenerException $e) {
            self::assertSame('broken', $e->getHandlerClass());
            self::assertSame(TestEvent::class, $e->getEventClass());
            self::assertNotNull($e->getPrevious());
        }
    }

    #[Test]
    public function it_throws_listener_exception_on_non_callable_handler(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, 'notCallable');

        $container = $this->createContainer(['notCallable' => 'just a string']);
        $dispatcher = new EventDispatcher($provider, $container, $this->createNullAsync(), new NullLogger());

        $this->expectException(ListenerException::class);
        $this->expectExceptionMessage('not callable');
        $dispatcher->dispatch(new TestEvent('fail'));
    }

    #[Test]
    public function it_throws_async_dispatch_exception_on_queue_failure(): void
    {
        $async = new class implements AsyncDispatcherInterface {
            public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
            {
                throw new \RuntimeException('Queue down');
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, 'asyncHandler', async: true);

        $dispatcher = new EventDispatcher($provider, $this->createEmptyContainer(), $async, new NullLogger());

        try {
            $dispatcher->dispatch(new TestEvent('fail'));
            self::fail('Expected AsyncDispatchException');
        } catch (AsyncDispatchException $e) {
            self::assertSame('asyncHandler', $e->getHandlerClass());
            self::assertSame(TestEvent::class, $e->getEventClass());
        }
    }

    #[Test]
    public function it_logs_successful_sync_dispatch(): void
    {
        $logMessages = [];
        $logger = new class ($logMessages) extends NullLogger {
            /** @param list<array{level: string, message: string}> $messages */
            public function __construct(private array &$messages)
            {
            }

            /** @param array<string, mixed> $context */
            public function debug(string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = ['level' => 'debug', 'message' => (string) $message];
            }
        };

        $handler = new class {
            public function __invoke(object $event): void
            {
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, 'handler');

        $container = $this->createContainer(['handler' => $handler]);
        $dispatcher = new EventDispatcher($provider, $container, $this->createNullAsync(), $logger);

        $dispatcher->dispatch(new TestEvent('log'));

        self::assertCount(1, $logMessages);
        self::assertSame('debug', $logMessages[0]['level']);
        self::assertSame('Listener executed', $logMessages[0]['message']);
    }

    #[Test]
    public function it_logs_errors_on_listener_failure(): void
    {
        $logMessages = [];
        $logger = new class ($logMessages) extends NullLogger {
            /** @param list<array{level: string, message: string}> $messages */
            public function __construct(private array &$messages)
            {
            }

            /** @param array<string, mixed> $context */
            public function error(string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = ['level' => 'error', 'message' => (string) $message];
            }
        };

        $handler = new class {
            public function __invoke(object $event): void
            {
                throw new \RuntimeException('broken');
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, 'broken');

        $container = $this->createContainer(['broken' => $handler]);
        $dispatcher = new EventDispatcher($provider, $container, $this->createNullAsync(), $logger);

        try {
            $dispatcher->dispatch(new TestEvent('fail'));
        } catch (ListenerException) {
        }

        self::assertCount(1, $logMessages);
        self::assertSame('error', $logMessages[0]['level']);
    }

    #[Test]
    public function it_dispatches_to_no_listeners_without_error(): void
    {
        $provider = new ListenerProvider();
        $dispatcher = new EventDispatcher($provider, $this->createEmptyContainer(), $this->createNullAsync(), new NullLogger());

        $event = new TestEvent('no listeners');
        $result = $dispatcher->dispatch($event);

        self::assertSame($event, $result);
    }

    #[Test]
    public function it_can_modify_event_in_listeners(): void
    {
        $handler = new class {
            public function __invoke(MutableEvent $event): void
            {
                $event->value = 'modified';
            }
        };

        $provider = new ListenerProvider();
        $provider->addListener(MutableEvent::class, 'handler');

        $container = $this->createContainer(['handler' => $handler]);
        $dispatcher = new EventDispatcher($provider, $container, $this->createNullAsync(), new NullLogger());

        $event = new MutableEvent();
        $dispatcher->dispatch($event);

        self::assertSame('modified', $event->value);
    }

    // ─── Helpers ───

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
                return $this->services[$id] ?? throw new class extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface {
                };
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    private function createEmptyContainer(): ContainerInterface
    {
        return $this->createContainer([]);
    }

    private function createNullAsync(): AsyncDispatcherInterface
    {
        return new class implements AsyncDispatcherInterface {
            public function publishAsync(object $event, string $handlerClass, int $priority = 0): void
            {
            }
        };
    }
}

// ─── Test Fixtures ───

final readonly class TestEvent
{
    public function __construct(public string $data)
    {
    }
}

final class TestStoppableEvent extends StoppableEvent
{
}

final class MutableEvent
{
    public string $value = 'original';
}

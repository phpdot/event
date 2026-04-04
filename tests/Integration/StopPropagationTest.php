<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Integration;

use PHPdot\Event\Contract\AsyncDispatcherInterface;
use PHPdot\Event\Event\StoppableEvent;
use PHPdot\Event\EventDispatcher;
use PHPdot\Event\ListenerProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StopPropagationTest extends TestCase
{
    #[Test]
    public function it_stops_after_first_match(): void
    {
        $log = [];

        $provider = new ListenerProvider();
        $provider->addListener(RouteMatchEvent::class, 'apiResolver', order: 1);
        $provider->addListener(RouteMatchEvent::class, 'webResolver', order: 2);
        $provider->addListener(RouteMatchEvent::class, 'fallbackResolver', order: 3);

        $services = [
            'apiResolver' => new class ($log) {
                /** @param list<string> $log */
                public function __construct(private array &$log)
                {
                }

                public function __invoke(RouteMatchEvent $event): void
                {
                    if (str_starts_with($event->path, '/api/')) {
                        $this->log[] = 'api';
                        $event->matched = 'api';
                        $event->stopPropagation();
                    }
                }
            },
            'webResolver' => new class ($log) {
                /** @param list<string> $log */
                public function __construct(private array &$log)
                {
                }

                public function __invoke(RouteMatchEvent $event): void
                {
                    $this->log[] = 'web';
                    $event->matched = 'web';
                }
            },
            'fallbackResolver' => new class ($log) {
                /** @param list<string> $log */
                public function __construct(private array &$log)
                {
                }

                public function __invoke(RouteMatchEvent $event): void
                {
                    $this->log[] = 'fallback';
                }
            },
        ];

        $dispatcher = $this->createDispatcher($provider, $services);

        $event = new RouteMatchEvent('/api/users');
        $dispatcher->dispatch($event);

        self::assertSame(['api'], $log);
        self::assertSame('api', $event->matched);
    }

    #[Test]
    public function it_continues_when_not_stopped(): void
    {
        $log = [];

        $provider = new ListenerProvider();
        $provider->addListener(RouteMatchEvent::class, 'apiResolver', order: 1);
        $provider->addListener(RouteMatchEvent::class, 'webResolver', order: 2);

        $services = [
            'apiResolver' => new class ($log) {
                /** @param list<string> $log */
                public function __construct(private array &$log)
                {
                }

                public function __invoke(RouteMatchEvent $event): void
                {
                    if (str_starts_with($event->path, '/api/')) {
                        $this->log[] = 'api';
                        $event->stopPropagation();
                    }
                    // Does NOT stop for non-api paths
                }
            },
            'webResolver' => new class ($log) {
                /** @param list<string> $log */
                public function __construct(private array &$log)
                {
                }

                public function __invoke(RouteMatchEvent $event): void
                {
                    $this->log[] = 'web';
                    $event->matched = 'web';
                }
            },
        ];

        $dispatcher = $this->createDispatcher($provider, $services);

        $event = new RouteMatchEvent('/about');
        $dispatcher->dispatch($event);

        self::assertSame(['web'], $log);
        self::assertSame('web', $event->matched);
    }

    #[Test]
    public function it_does_not_stop_non_stoppable_events(): void
    {
        $log = [];

        $provider = new ListenerProvider();
        $provider->addListener(NonStoppableEvent::class, 'handlerA', order: 1);
        $provider->addListener(NonStoppableEvent::class, 'handlerB', order: 2);

        $services = [
            'handlerA' => new class ($log) {
                /** @param list<string> $log */
                public function __construct(private array &$log)
                {
                }

                public function __invoke(object $event): void
                {
                    $this->log[] = 'A';
                }
            },
            'handlerB' => new class ($log) {
                /** @param list<string> $log */
                public function __construct(private array &$log)
                {
                }

                public function __invoke(object $event): void
                {
                    $this->log[] = 'B';
                }
            },
        ];

        $dispatcher = $this->createDispatcher($provider, $services);
        $dispatcher->dispatch(new NonStoppableEvent());

        self::assertSame(['A', 'B'], $log);
    }

    #[Test]
    public function it_also_stops_async_dispatches(): void
    {
        $asyncPublished = false;

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
        $provider->addListener(RouteMatchEvent::class, 'stopper', order: 1);
        $provider->addListener(RouteMatchEvent::class, 'asyncHandler', order: 2, async: true);

        $stopper = new class {
            public function __invoke(RouteMatchEvent $event): void
            {
                $event->stopPropagation();
            }
        };

        $container = $this->createContainer(['stopper' => $stopper]);
        $dispatcher = new EventDispatcher($provider, $container, $async, new NullLogger());

        $dispatcher->dispatch(new RouteMatchEvent('/test'));

        self::assertFalse($asyncPublished);
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

// ─── Fixtures ───

final class RouteMatchEvent extends StoppableEvent
{
    public string $matched = '';

    public function __construct(
        public readonly string $path,
    ) {
    }
}

final class NonStoppableEvent
{
}

<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Unit;

use PHPdot\Event\Provider\SyncOnlyDispatcher;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SyncOnlyDispatcherTest extends TestCase
{
    #[Test]
    public function it_executes_handler_synchronously(): void
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

        $container = $this->createContainer(['handler' => $handler]);
        $dispatcher = new SyncOnlyDispatcher($container);

        $event = new \stdClass();
        $dispatcher->publishAsync($event, 'handler', 5);

        self::assertTrue($called);
    }

    #[Test]
    public function it_ignores_priority(): void
    {
        $receivedEvent = null;
        $handler = new class ($receivedEvent) {
            public function __construct(private ?object &$event)
            {
            }

            public function __invoke(object $event): void
            {
                $this->event = $event;
            }
        };

        $container = $this->createContainer(['handler' => $handler]);
        $dispatcher = new SyncOnlyDispatcher($container);

        $event = new \stdClass();
        $event->data = 'test';
        $dispatcher->publishAsync($event, 'handler', 10);

        self::assertNotNull($receivedEvent);
        self::assertSame('test', $receivedEvent->data);
    }

    #[Test]
    public function it_throws_on_non_callable_handler(): void
    {
        $container = $this->createContainer(['bad' => 'not callable']);
        $dispatcher = new SyncOnlyDispatcher($container);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not callable');

        $dispatcher->publishAsync(new \stdClass(), 'bad');
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

<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Unit;

use PHPdot\Event\Exception\AsyncDispatchException;
use PHPdot\Event\Exception\EventException;
use PHPdot\Event\Exception\ListenerException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    #[Test]
    public function event_exception_is_runtime_exception(): void
    {
        $e = new EventException('test');

        self::assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function listener_exception_carries_context(): void
    {
        $previous = new \RuntimeException('root');
        $e = new ListenerException(
            'Handler failed',
            'App\Listener\SendEmail',
            'App\Event\UserRegistered',
            previous: $previous,
        );

        self::assertSame('Handler failed', $e->getMessage());
        self::assertSame('App\Listener\SendEmail', $e->getHandlerClass());
        self::assertSame('App\Event\UserRegistered', $e->getEventClass());
        self::assertSame($previous, $e->getPrevious());
        self::assertInstanceOf(EventException::class, $e);
    }

    #[Test]
    public function async_dispatch_exception_carries_context(): void
    {
        $e = new AsyncDispatchException(
            'Queue failed',
            'App\Listener\SyncToMailchimp',
            'App\Event\UserRegistered',
            42,
        );

        self::assertSame('Queue failed', $e->getMessage());
        self::assertSame('App\Listener\SyncToMailchimp', $e->getHandlerClass());
        self::assertSame('App\Event\UserRegistered', $e->getEventClass());
        self::assertSame(42, $e->getCode());
        self::assertInstanceOf(EventException::class, $e);
    }

    #[Test]
    public function listener_exception_is_final(): void
    {
        self::assertTrue((new \ReflectionClass(ListenerException::class))->isFinal());
    }

    #[Test]
    public function async_dispatch_exception_is_final(): void
    {
        self::assertTrue((new \ReflectionClass(AsyncDispatchException::class))->isFinal());
    }

    #[Test]
    public function event_exception_is_not_final(): void
    {
        self::assertFalse((new \ReflectionClass(EventException::class))->isFinal());
    }
}

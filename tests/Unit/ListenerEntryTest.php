<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Unit;

use PHPdot\Event\DTO\ListenerEntry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListenerEntryTest extends TestCase
{
    #[Test]
    public function it_stores_all_fields(): void
    {
        $entry = new ListenerEntry(
            eventClass: 'App\Event\UserRegistered',
            handlerClass: 'App\Listener\SendEmail',
            order: 3,
            async: true,
            priority: 7,
            enabled: false,
        );

        self::assertSame('App\Event\UserRegistered', $entry->eventClass);
        self::assertSame('App\Listener\SendEmail', $entry->handlerClass);
        self::assertSame(3, $entry->order);
        self::assertTrue($entry->async);
        self::assertSame(7, $entry->priority);
        self::assertFalse($entry->enabled);
    }

    #[Test]
    public function it_has_sensible_defaults(): void
    {
        $entry = new ListenerEntry(
            eventClass: 'App\Event\UserRegistered',
            handlerClass: 'App\Listener\SendEmail',
        );

        self::assertSame(0, $entry->order);
        self::assertFalse($entry->async);
        self::assertSame(0, $entry->priority);
        self::assertTrue($entry->enabled);
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $reflection = new \ReflectionClass(ListenerEntry::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }
}

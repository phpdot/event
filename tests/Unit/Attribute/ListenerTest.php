<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Unit\Attribute;

use PHPdot\Event\Attribute\Listener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListenerTest extends TestCase
{
    #[Test]
    public function it_stores_event_class(): void
    {
        $listener = new Listener(event: 'App\Event\UserRegistered');

        self::assertSame('App\Event\UserRegistered', $listener->event);
    }

    #[Test]
    public function it_has_sensible_defaults(): void
    {
        $listener = new Listener(event: 'App\Event\UserRegistered');

        self::assertSame(0, $listener->order);
        self::assertFalse($listener->async);
        self::assertSame(0, $listener->priority);
    }

    #[Test]
    public function it_accepts_all_parameters(): void
    {
        $listener = new Listener(
            event: 'App\Event\OrderPlaced',
            order: 5,
            async: true,
            priority: 8,
        );

        self::assertSame('App\Event\OrderPlaced', $listener->event);
        self::assertSame(5, $listener->order);
        self::assertTrue($listener->async);
        self::assertSame(8, $listener->priority);
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $reflection = new \ReflectionClass(Listener::class);

        self::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function it_targets_classes_and_is_repeatable(): void
    {
        $reflection = new \ReflectionClass(Listener::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attr = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE, $attr->flags);
    }

    #[Test]
    public function it_can_be_read_from_class_via_reflection(): void
    {
        $class = new class {
        };

        // Create a real attributed class inline — use reflection on a fixture
        $reflection = new \ReflectionClass(SingleListenerFixture::class);
        $attributes = $reflection->getAttributes(Listener::class);

        self::assertCount(1, $attributes);

        $listener = $attributes[0]->newInstance();
        self::assertSame('TestEvent', $listener->event);
        self::assertSame(1, $listener->order);
    }

    #[Test]
    public function it_supports_multiple_attributes_on_same_class(): void
    {
        $reflection = new \ReflectionClass(MultiListenerFixture::class);
        $attributes = $reflection->getAttributes(Listener::class);

        self::assertCount(2, $attributes);

        $first = $attributes[0]->newInstance();
        $second = $attributes[1]->newInstance();

        self::assertSame('EventA', $first->event);
        self::assertSame('EventB', $second->event);
    }

    #[Test]
    public function it_supports_async_attribute(): void
    {
        $reflection = new \ReflectionClass(AsyncListenerFixture::class);
        $attributes = $reflection->getAttributes(Listener::class);

        $listener = $attributes[0]->newInstance();
        self::assertTrue($listener->async);
        self::assertSame(5, $listener->priority);
    }
}

#[Listener(event: 'TestEvent', order: 1)]
final class SingleListenerFixture
{
}

#[Listener(event: 'EventA', order: 1)]
#[Listener(event: 'EventB', order: 2)]
final class MultiListenerFixture
{
}

#[Listener(event: 'AsyncEvent', async: true, priority: 5)]
final class AsyncListenerFixture
{
}

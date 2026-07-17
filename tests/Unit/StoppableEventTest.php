<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Unit;

use PHPdot\Event\Event\StoppableEvent;
use Psr\EventDispatcher\StoppableEventInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StoppableEventTest extends TestCase
{
    #[Test]
    public function it_is_not_stopped_by_default(): void
    {
        $event = new ConcreteStoppableEvent();

        self::assertFalse($event->isPropagationStopped());
    }

    #[Test]
    public function it_can_be_stopped(): void
    {
        $event = new ConcreteStoppableEvent();
        $event->stopPropagation();

        self::assertTrue($event->isPropagationStopped());
    }

    #[Test]
    public function it_implements_psr14_interface(): void
    {
        $event = new ConcreteStoppableEvent();

        self::assertInstanceOf(StoppableEventInterface::class, $event);
    }

    #[Test]
    public function it_stays_stopped_after_multiple_calls(): void
    {
        $event = new ConcreteStoppableEvent();
        $event->stopPropagation();
        $event->stopPropagation();

        self::assertTrue($event->isPropagationStopped());
    }
}

final class ConcreteStoppableEvent extends StoppableEvent
{
    public function __construct(
        public readonly string $data = 'test',
    ) {
    }
}

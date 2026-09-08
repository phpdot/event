<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Integration;

use PHPdot\Event\Discovery\AttributeListenerDiscovery;
use PHPdot\Event\Exception\ListenerException;
use PHPdot\Event\Tests\Fixtures\Discovery\Valid\OrderPlaced;
use PHPdot\Event\Tests\Fixtures\Discovery\Valid\UserRegistered;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The discovery the #[Listener] attribute always promised: repeatable
 * attributes read from real scanned classes, with the fail-loud laws — an
 * event that is not a class and a priority outside 0-10 are boot errors
 * naming the listener.
 */
final class AttributeListenerDiscoveryTest extends TestCase
{
    private const string VALID = __DIR__ . '/../Fixtures/Discovery/Valid';

    #[Test]
    public function discoversEveryAttributeIncludingRepeatedOnes(): void
    {
        $entries = (new AttributeListenerDiscovery([self::VALID]))->discover();

        $byKey = [];

        foreach ($entries as $entry) {
            $byKey[$entry->eventClass . '::' . $entry->handlerClass] = $entry;
        }

        self::assertArrayHasKey(UserRegistered::class . '::PHPdot\Event\Tests\Fixtures\Discovery\Valid\SendWelcomeEmail', $byKey);
        self::assertArrayHasKey(UserRegistered::class . '::PHPdot\Event\Tests\Fixtures\Discovery\Valid\AuditSink', $byKey);
        self::assertArrayHasKey(OrderPlaced::class . '::PHPdot\Event\Tests\Fixtures\Discovery\Valid\FulfillOrder', $byKey);

        $audit = $byKey[UserRegistered::class . '::PHPdot\Event\Tests\Fixtures\Discovery\Valid\AuditSink'];

        self::assertTrue($audit->async);
        self::assertSame(20, $audit->order);
        self::assertSame(5, $audit->priority);
    }

    #[Test]
    public function aNonexistentEventFailsLoudNamingTheListener(): void
    {
        $this->expectException(ListenerException::class);
        $this->expectExceptionMessage('which is not a class');

        (new AttributeListenerDiscovery([__DIR__ . '/../Fixtures/Discovery/Invalid/BrokenEvent']))->discover();
    }

    #[Test]
    public function aPriorityOutsideTheRangeFailsLoud(): void
    {
        $this->expectException(ListenerException::class);
        $this->expectExceptionMessage('0-10');

        (new AttributeListenerDiscovery([__DIR__ . '/../Fixtures/Discovery/Invalid/Priority']))->discover();
    }

    #[Test]
    public function anUntypedCatchAllHandlerIsRefusedAtDiscovery(): void
    {
        $this->expectException(ListenerException::class);
        $this->expectExceptionMessage('cannot receive it');

        (new AttributeListenerDiscovery([__DIR__ . '/../Fixtures/Discovery/Invalid/Untyped']))->discover();
    }

    #[Test]
    public function anEmptyDirectoryDiscoversNothing(): void
    {
        $empty = sys_get_temp_dir() . '/event-empty-' . uniqid();
        mkdir($empty);

        try {
            self::assertSame([], (new AttributeListenerDiscovery([$empty]))->discover());
        } finally {
            rmdir($empty);
        }
    }
}

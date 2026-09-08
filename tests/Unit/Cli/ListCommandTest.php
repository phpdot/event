<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Unit\Cli;

use PHPdot\Event\Cli\ListCommand;
use PHPdot\Event\Contract\ListenerDiscoveryInterface;
use PHPdot\Event\DTO\ListenerEntry;
use PHPdot\Event\Tests\Fixtures\Discovery\Valid\OrderPlaced;
use PHPdot\Event\Tests\Fixtures\Discovery\Valid\UserRegistered;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The event:list surface: what boot would load, one row per binding, with
 * the async listeners marked and their priority shown.
 */
final class ListCommandTest extends TestCase
{
    #[Test]
    public function showsEveryDiscoveredListenerWithOrderAndMode(): void
    {
        $tester = new CommandTester(new ListCommand($this->discovery([
            new ListenerEntry(UserRegistered::class, 'App\Welcome', order: 10),
            new ListenerEntry(UserRegistered::class, 'App\Audit', order: 20, async: true, priority: 5),
        ])));

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();

        self::assertStringContainsString('Welcome', $display);
        self::assertStringContainsString('sync', $display);
        self::assertStringContainsString('Audit', $display);
        self::assertStringContainsString('async', $display);
        self::assertStringContainsString('2 listeners declared', $display);
    }

    #[Test]
    public function theEventFilterLimitsToMatchingEvents(): void
    {
        $tester = new CommandTester(new ListCommand($this->discovery([
            new ListenerEntry(UserRegistered::class, 'App\Welcome'),
            new ListenerEntry(OrderPlaced::class, 'App\Fulfill'),
        ])));

        self::assertSame(Command::SUCCESS, $tester->execute(['--event' => 'Order']));

        $display = $tester->getDisplay();

        self::assertStringContainsString('Fulfill', $display);
        self::assertStringNotContainsString('Welcome', $display);
    }

    #[Test]
    public function noListenersSaysSoAndSucceeds(): void
    {
        $tester = new CommandTester(new ListCommand($this->discovery([])));

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        self::assertStringContainsString('No listeners are declared', $tester->getDisplay());
    }

    /**
     * @param list<ListenerEntry> $entries
     */
    private function discovery(array $entries): ListenerDiscoveryInterface
    {
        return new class ($entries) implements ListenerDiscoveryInterface {
            /**
             * @param list<ListenerEntry> $entries
             */
            public function __construct(private readonly array $entries) {}

            public function discover(): array
            {
                return $this->entries;
            }
        };
    }
}

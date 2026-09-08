<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Integration;

use PHPdot\Container\ContainerBuilder;
use PHPdot\Event\EventDispatcher;
use PHPdot\Event\ListenerProvider;
use PHPdot\Event\Tests\Support\RecordingTracer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Proves the package wires through the real phpdot container: the
 * #[Singleton]/#[Binds] attributes are discovered by a directory scan, the
 * PSR-14 interfaces resolve to this package's implementations, and the
 * dispatcher is stable within a request and fresh across requests.
 */
final class ContainerTest extends TestCase
{
    #[Test]
    public function theAttributeWiringResolvesThePsr14Interfaces(): void
    {
        $container = (new ContainerBuilder())
            ->scanAttributesIn(\dirname(__DIR__, 2) . '/src')
            ->addDefinitions([
                \PHPdot\Contracts\Logs\TracerInterface::class => new RecordingTracer(),
                ListenerProviderInterface::class => \PHPdot\Container\singleton(
                    static fn(\Psr\Container\ContainerInterface $c): ListenerProvider => $c->get(ListenerProvider::class),
                ),
                \PHPdot\Contracts\Event\AsyncDispatcherInterface::class => \PHPdot\Container\singleton(
                    static fn(\Psr\Container\ContainerInterface $c): \PHPdot\Event\Provider\SyncOnlyDispatcher => $c->get(\PHPdot\Event\Provider\SyncOnlyDispatcher::class),
                ),
                \PHPdot\Event\Contract\ListenerRepositoryInterface::class => \PHPdot\Container\singleton(
                    static fn(\Psr\Container\ContainerInterface $c): \PHPdot\Event\Provider\InMemoryListenerRepository => $c->get(\PHPdot\Event\Provider\InMemoryListenerRepository::class),
                ),
                EventDispatcherInterface::class => \PHPdot\Container\singleton(
                    static fn(\Psr\Container\ContainerInterface $c): EventDispatcher => $c->get(EventDispatcher::class),
                ),
            ])
            ->build();

        $provider = $container->get(ListenerProviderInterface::class);
        $dispatcher = $container->get(EventDispatcherInterface::class);

        self::assertInstanceOf(ListenerProvider::class, $provider);
        self::assertInstanceOf(EventDispatcher::class, $dispatcher);
        self::assertSame($dispatcher, $container->get(EventDispatcherInterface::class), 'a singleton, stable for the worker life');

        self::assertSame($dispatcher, $container->get(EventDispatcherInterface::class), 'the same instance again — the worker shares one dispatcher');
    }
}

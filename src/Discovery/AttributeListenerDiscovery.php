<?php

declare(strict_types=1);

/**
 * The discovery the #[Listener] attribute always promised: scan directories
 * with phpdot/attribute, read every class-level Listener attribute (they are
 * repeatable — one handler may listen to many events), and answer the
 * ListenerEntry list the provider loads at boot. Fail-loud: a declared event
 * that is not a class is a boot error naming the listener, never a silent
 * listener that never fires.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Event\Discovery;

use PHPdot\Attribute\Registry;
use PHPdot\Attribute\Scanner;
use PHPdot\Event\Attribute\Listener;
use PHPdot\Event\Contract\ListenerDiscoveryInterface;
use PHPdot\Event\DTO\ListenerEntry;
use PHPdot\Event\Exception\ListenerException;
use ReflectionMethod;
use ReflectionNamedType;

final class AttributeListenerDiscovery implements ListenerDiscoveryInterface
{
    /**
     * @param list<string> $directories Application directories to scan
     */
    public function __construct(
        private readonly array $directories,
    ) {}

    /**
     * Discover every listener entry in the scanned directories.
     *
     * @return list<ListenerEntry>
     */
    public function discover(): array
    {
        $entries = [];

        foreach ($this->registry()->findClassAttributes(Listener::class) as $result) {
            $listener = $result->instance;

            if (!$listener instanceof Listener) {
                continue;
            }

            if (!class_exists($listener->event) && !interface_exists($listener->event)) {
                throw ListenerException::unknownEvent($listener->event, $result->class);
            }

            if ($listener->priority < 0 || $listener->priority > 10) {
                throw ListenerException::invalidPriority($listener->event, $result->class, $listener->priority);
            }

            if (!$this->receives($result->class, $listener->event)) {
                throw ListenerException::shapeMismatch($result->class, $listener->event);
            }

            $entries[] = new ListenerEntry(
                eventClass: $listener->event,
                handlerClass: $result->class,
                order: $listener->order,
                async: $listener->async,
                priority: $listener->priority,
            );
        }

        return $entries;
    }

    /**
     * The typed-listener law: the handler's __invoke must declare exactly one
     * parameter accepting the event — the event class itself, a parent, an
     * interface, or an untyped parameter. A handler that cannot receive its
     * event is a boot error, never a listener that silently never fires or a
     * catch-all behind an untyped door.
     *
     * @param string $handlerClass The listener class
     * @param string $eventClass The declared event
     *
     * @return bool
     */
    private function receives(string $handlerClass, string $eventClass): bool
    {
        if (!method_exists($handlerClass, '__invoke')) {
            return false;
        }

        $parameters = (new ReflectionMethod($handlerClass, '__invoke'))->getParameters();

        if (count($parameters) !== 1) {
            return false;
        }

        $type = $parameters[0]->getType();

        if (!$type instanceof ReflectionNamedType) {
            return true;
        }

        $parameterType = $type->getName();

        if ($parameterType === $eventClass || is_subclass_of($eventClass, $parameterType)) {
            return true;
        }

        $implements = class_implements($eventClass);

        return is_array($implements) && interface_exists($parameterType) && in_array($parameterType, $implements, true);
    }

    /**
     * The scan registry over the configured directories.
     *
     * @return Registry
     */
    private function registry(): Registry
    {
        return (new Scanner())->scan($this->directories);
    }
}

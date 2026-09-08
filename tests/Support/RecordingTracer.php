<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Support;

use PHPdot\Contracts\Logs\PendingLogInterface;
use PHPdot\Contracts\Logs\SpanContextInterface;
use PHPdot\Contracts\Logs\SpanInterface;
use PHPdot\Contracts\Logs\TracerInterface;

/**
 * A tracer that records spans and their events so dispatch observability can
 * be asserted without a logging backend.
 */
final class RecordingTracer implements TracerInterface
{
    /**
     * @var list<array{name: string, attributes: array<string, string|int|float|bool>, events: list<array{name: string, attributes: array<string, string|int|float|bool>}>}>
     */
    public array $spans = [];

    public function channel(string $name): self
    {
        return $this;
    }

    public function span(string $name, string $kind = 'internal'): SpanInterface
    {
        $recorder = $this;
        $index = count($this->spans);
        $this->spans[] = ['name' => $name, 'attributes' => [], 'events' => []];

        return new class ($recorder, $index) implements SpanInterface {
            public function __construct(
                private readonly RecordingTracer $recorder,
                private readonly int $index,
            ) {}

            public function setAttribute(string $key, string|int|float|bool $value): static
            {
                $this->recorder->spans[$this->index]['attributes'][$key] = $value;

                return $this;
            }

            public function addEvent(string $name, array $attributes = []): static
            {
                $this->recorder->spans[$this->index]['events'][] = ['name' => $name, 'attributes' => $attributes];

                return $this;
            }

            public function setStatus(string $status, string $description = ''): static
            {
                return $this;
            }

            public function status(): string
            {
                return 'unset';
            }

            public function context(): SpanContextInterface
            {
                throw new \RuntimeException('not needed in the event fixtures');
            }

            public function end(): void {}

            public function debug(string $message, array $context = []): PendingLogInterface
            {
                throw new \RuntimeException('not needed in the event fixtures');
            }

            public function info(string $message, array $context = []): PendingLogInterface
            {
                throw new \RuntimeException('not needed in the event fixtures');
            }

            public function notice(string $message, array $context = []): PendingLogInterface
            {
                throw new \RuntimeException('not needed in the event fixtures');
            }

            public function warning(string $message, array $context = []): PendingLogInterface
            {
                throw new \RuntimeException('not needed in the event fixtures');
            }

            public function error(string $message, array $context = []): PendingLogInterface
            {
                throw new \RuntimeException('not needed in the event fixtures');
            }

            public function critical(string $message, array $context = []): PendingLogInterface
            {
                throw new \RuntimeException('not needed in the event fixtures');
            }

            public function alert(string $message, array $context = []): PendingLogInterface
            {
                throw new \RuntimeException('not needed in the event fixtures');
            }

            public function emergency(string $message, array $context = []): PendingLogInterface
            {
                throw new \RuntimeException('not needed in the event fixtures');
            }
        };
    }

    public function current(): SpanInterface
    {
        return $this->span('current');
    }

    public function context(): SpanContextInterface
    {
        throw new \RuntimeException('not needed in the event fixtures');
    }

    public function trace(string $name, string $kind, callable $callback): mixed
    {
        return $callback();
    }

    public function debug(string $message, array $context = []): PendingLogInterface
    {
        throw new \RuntimeException('not needed in the event fixtures');
    }

    public function info(string $message, array $context = []): PendingLogInterface
    {
        throw new \RuntimeException('not needed in the event fixtures');
    }

    public function notice(string $message, array $context = []): PendingLogInterface
    {
        throw new \RuntimeException('not needed in the event fixtures');
    }

    public function warning(string $message, array $context = []): PendingLogInterface
    {
        throw new \RuntimeException('not needed in the event fixtures');
    }

    public function error(string $message, array $context = []): PendingLogInterface
    {
        throw new \RuntimeException('not needed in the event fixtures');
    }

    public function critical(string $message, array $context = []): PendingLogInterface
    {
        throw new \RuntimeException('not needed in the event fixtures');
    }

    public function alert(string $message, array $context = []): PendingLogInterface
    {
        throw new \RuntimeException('not needed in the event fixtures');
    }

    public function emergency(string $message, array $context = []): PendingLogInterface
    {
        throw new \RuntimeException('not needed in the event fixtures');
    }
}

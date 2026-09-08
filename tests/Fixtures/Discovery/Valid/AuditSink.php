<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Fixtures\Discovery\Valid;

use PHPdot\Event\Attribute\Listener;

#[Listener(event: UserRegistered::class, order: 20, async: true, priority: 5)]
final class AuditSink
{
    public function __invoke(UserRegistered $event): void {}
}

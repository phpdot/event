<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Fixtures\Discovery\Invalid\Priority;

use PHPdot\Event\Attribute\Listener;
use PHPdot\Event\Tests\Fixtures\Discovery\Valid\UserRegistered;

#[Listener(event: UserRegistered::class, priority: 99)]
final class PriorityOutOfRange
{
    public function __invoke(object $event): void {}
}

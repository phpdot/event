<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Fixtures\Discovery\Invalid\Untyped;

use PHPdot\Event\Attribute\Listener;
use PHPdot\Event\Tests\Fixtures\Discovery\Valid\UserRegistered;

#[Listener(event: UserRegistered::class)]
final class CatchAll
{
    public function __invoke(object $event): void {}
}

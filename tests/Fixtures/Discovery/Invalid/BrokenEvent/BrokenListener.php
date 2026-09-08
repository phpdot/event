<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Fixtures\Discovery\Invalid\BrokenEvent;

use PHPdot\Event\Attribute\Listener;

#[Listener(event: 'Not\\A\\Real\\Event')]
final class BrokenListener
{
    public function __invoke(object $event): void {}
}

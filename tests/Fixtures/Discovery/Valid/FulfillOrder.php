<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Fixtures\Discovery\Valid;

use PHPdot\Event\Attribute\Listener;

#[Listener(event: OrderPlaced::class, order: 5)]
final class FulfillOrder
{
    public function __invoke(OrderPlaced $event): void {}
}

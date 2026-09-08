<?php

declare(strict_types=1);

namespace PHPdot\Event\Tests\Fixtures\Discovery\Valid;

final class UserRegistered
{
    public function __construct(public string $userId) {}
}

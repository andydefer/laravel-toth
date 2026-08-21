<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Fixtures\Enums;

enum TestUserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
}

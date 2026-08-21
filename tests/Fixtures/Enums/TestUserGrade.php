<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Fixtures\Enums;

enum TestUserGrade: int
{
    case BRONZE = 1;
    case SILVER = 2;
    case GOLD = 3;
    case PLATINUM = 4;
}

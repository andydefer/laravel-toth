<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelToth\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\LaravelToth\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\LaravelToth\Tests\Fixtures\Enums\TestUserStatus;

final class TestUserFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?TestUserStatus $status = null,
        public readonly ?TestUserRole $role = null,
        public readonly ?TestUserGrade $grade = null,
        public readonly ?string $cluster_query = null,
    ) {}
}

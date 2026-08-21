<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class TestProductFilterRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?float $min_price = null,
        public readonly ?float $max_price = null,
        public readonly ?bool $is_active = null,
        public readonly ?bool $is_deleted = null,
        public readonly ?string $cluster_query = null,
    ) {}
}

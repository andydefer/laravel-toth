<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class TestProductRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?float $price = null,
        public readonly ?int $stock = null,
        public readonly ?bool $is_active = null,
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null,
        public readonly ?string $deleted_at = null,
        public readonly ?ClusterVO $metadata = null,
    ) {}
}

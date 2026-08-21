<?php

// src/Records/ArchiveFiltersRecord.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class ArchiveFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $table_name = null,
        public readonly ?string $row_id = null,
        public readonly ?string $model_class = null,
        public readonly ?string $search = null,
        public readonly ?string $from_date = null,
        public readonly ?string $to_date = null,
    ) {}
}

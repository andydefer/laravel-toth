<?php

// src/Records/ArchiveRecord.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

final class ArchiveRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $table_name = null,
        public readonly ?string $row_id = null,
        public readonly ?string $model_class = null,
        public readonly ?StrictAssociative $data = null,
        public readonly ?string $last_save_at = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Filter criteria for querying Archive records.
 *
 * All properties are optional and will be used as AND conditions when applied.
 * Supports filtering by ID, table name, row ID, model class, date range, and free-text search.
 */
final class ArchiveFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $table_name = null,
        public readonly ?string $row_id = null,
        public readonly ?string $model_class = null,
        public readonly ?string $search = null,
        public readonly ?DateTimeVO $from_date = null,
        public readonly ?DateTimeVO $to_date = null,
    ) {}
}

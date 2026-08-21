<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Record representing the data structure for creating or updating an Archive.
 *
 * This record is used as a data transfer object between the service layer
 * and the repository. All properties are optional to allow partial updates.
 */
final class ArchiveRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $table_name = null,
        public readonly ?string $row_id = null,
        public readonly ?string $model_class = null,
        public readonly ?ClusterVO $data = null,
        public readonly ?DateTimeVO $last_save_at = null,
    ) {}
}

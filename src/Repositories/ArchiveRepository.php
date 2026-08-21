<?php

// src/Repositories/ArchiveRepository.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Records\ArchiveFiltersRecord;
use AndyDefer\LaravelToth\Records\ArchiveRecord;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;

final class ArchiveRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(Archive::class, ArchiveRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof ArchiveFiltersRecord) {
            return;
        }

        $query->when($filters->id, fn ($q, $id) => $q->where('id', $id)
        );

        $query->when($filters->table_name, fn ($q, $tableName) => $q->where('table_name', $tableName)
        );

        $query->when($filters->row_id, fn ($q, $rowId) => $q->where('row_id', $rowId)
        );

        $query->when($filters->model_class, fn ($q, $modelClass) => $q->where('model_class', $modelClass)
        );

        $query->when($filters->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
            $q->where('table_name', 'like', "%{$search}%")
                ->orWhere('row_id', 'like', "%{$search}%")
                ->orWhere('model_class', 'like', "%{$search}%")
                ->orWhere('data', 'like', "%{$search}%");
        })
        );

        $query->when($filters->from_date, fn ($q, $date) => $q->whereDate('last_save_at', '>=', $date)
        );

        $query->when($filters->to_date, fn ($q, $date) => $q->whereDate('last_save_at', '<=', $date)
        );
    }
}

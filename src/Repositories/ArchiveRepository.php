<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Records\ArchiveFiltersRecord;
use AndyDefer\LaravelToth\Records\ArchiveRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Records\FindByRecord;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repository for Archive model operations.
 *
 * Provides specialized query methods for archives including filtering by
 * table name, row ID, model class, and date ranges.
 */
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

        $query->when($filters->id, fn ($q, $id) => $q->where('id', $id));
        $query->when($filters->table_name, fn ($q, $tableName) => $q->where('table_name', $tableName));
        $query->when($filters->row_id, fn ($q, $rowId) => $q->where('row_id', $rowId));
        $query->when($filters->model_class, fn ($q, $modelClass) => $q->where('model_class', $modelClass));

        $query->when($filters->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
            $q->where('table_name', 'like', "%{$search}%")
                ->orWhere('row_id', 'like', "%{$search}%")
                ->orWhere('model_class', 'like', "%{$search}%")
                ->orWhere('data', 'like', "%{$search}%");
        }));

        $query->when(
            $filters->from_date,
            fn ($q, DateTimeVO $date) => $q->whereDate('last_save_at', '>=', $date->toDateString())
        );

        $query->when(
            $filters->to_date,
            fn ($q, DateTimeVO $date) => $q->whereDate('last_save_at', '<=', $date->toDateString())
        );
    }

    /**
     * Updates an existing archive or creates a new one if none exists.
     *
     * @param  array<string, mixed>  $attributes  The attributes to match for existence
     * @param  array<string, mixed>  $values  The values to set on creation or update
     * @return Archive The updated or created archive
     */
    public function updateOrCreate(array $attributes, array $values): Archive
    {
        $filters = ArchiveFiltersRecord::from([
            'table_name' => $attributes['table_name'] ?? null,
            'row_id' => $attributes['row_id'] ?? null,
            'model_class' => $attributes['model_class'] ?? null,
        ]);

        $existing = $this->findBy(
            FindByRecord::from(['filters' => $filters])
        )->first();

        if ($existing) {
            $existing->update($values);

            return $existing->fresh();
        }

        $record = ArchiveRecord::from(array_merge($attributes, $values));

        return $this->create($record);
    }
}

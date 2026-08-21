<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Observers;

use AndyDefer\LaravelToth\Services\ArchiveService;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer that automatically archives Eloquent models on lifecycle events.
 *
 * This observer is attached to all archivable models defined in the configuration.
 * It triggers the archiving process whenever a model is created, updated, or deleted.
 */
final class ArchivableObserver
{
    public function __construct(
        private readonly ArchiveService $archiveService,
    ) {}

    /**
     * Handles the "created" event for an archivable model.
     *
     * @param  Model  $model  The model that was created
     */
    public function created(Model $model): void
    {
        $this->archiveService->createOrUpdateArchive($model);
    }

    /**
     * Handles the "updated" event for an archivable model.
     *
     * @param  Model  $model  The model that was updated
     */
    public function updated(Model $model): void
    {
        $this->archiveService->createOrUpdateArchive($model);
    }

    /**
     * Handles the "deleted" event for an archivable model.
     *
     * @param  Model  $model  The model that was deleted
     */
    public function deleted(Model $model): void
    {
        $this->archiveService->createOrUpdateArchive($model);
    }
}

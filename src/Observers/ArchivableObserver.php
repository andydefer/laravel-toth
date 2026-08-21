<?php

// src/Observers/ArchivableObserver.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Observers;

use AndyDefer\LaravelToth\Services\ArchiveService;
use Illuminate\Database\Eloquent\Model;

final class ArchivableObserver
{
    public function __construct(
        private readonly ArchiveService $archiveService
    ) {}

    public function created(Model $model): void
    {
        $this->archiveService->createOrUpdateArchive($model);
    }

    public function updated(Model $model): void
    {
        $this->archiveService->createOrUpdateArchive($model);
    }

    public function deleted(Model $model): void
    {
        $this->archiveService->createOrUpdateArchive($model);
    }
}

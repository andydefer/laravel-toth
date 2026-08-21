<?php

// src/Contracts/Services/ArchiveServiceInterface.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Contracts\Services;

use AndyDefer\LaravelToth\Models\Archive;
use Illuminate\Database\Eloquent\Model;

interface ArchiveServiceInterface
{
    /**
     * Create or update an archive for a model.
     */
    public function createOrUpdateArchive(Model $model): ?Archive;

    /**
     * Backup a single archive (create file backup).
     */
    public function backup(Archive $archive): void;

    /**
     * Backup all models from database.
     */
    public function backupFromModels(array $tables = []): void;

    /**
     * Backup all archives from storage files.
     */
    public function backupFromFiles(array $tables = []): void;

    /**
     * Restore all models from database archives.
     */
    public function restoreFromModels(array $tables = []): void;

    /**
     * Restore all models from storage files.
     */
    public function restoreFromFiles(array $tables = []): void;

    /**
     * Register observers for all archivable models.
     */
    public function registerObservers(): void;
}

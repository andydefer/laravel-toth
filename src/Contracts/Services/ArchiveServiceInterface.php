<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Contracts\Services;

use AndyDefer\LaravelToth\Models\Archive;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for the archive management service.
 *
 * Defines the core operations for creating, backing up, restoring,
 * and managing archives of Eloquent models.
 */
interface ArchiveServiceInterface
{
    /**
     * Creates or updates an archive entry for a given model.
     *
     * This method triggers the archiving process asynchronously via tasks.
     *
     * @param  Model  $model  The model to archive
     * @return Archive|null The created or updated archive, or null if not found
     */
    public function createOrUpdateArchive(Model $model): ?Archive;

    /**
     * Creates a physical file backup for a single archive.
     *
     * The backup file is stored in the configured backup folder path.
     *
     * @param  Archive  $archive  The archive to backup
     */
    public function backup(Archive $archive): void;

    /**
     * Creates archives for all configured models from the database.
     *
     * @param  array<string>  $tables  Optional list of table names to filter by
     */
    public function backupFromModels(array $tables = []): void;

    /**
     * Creates archives from existing backup files in storage.
     *
     * Reads backup files from the configured backup folder and recreates
     * the corresponding archive entries.
     *
     * @param  array<string>  $tables  Optional list of table names to filter by
     */
    public function backupFromFiles(array $tables = []): void;

    /**
     * Restores models from database archives.
     *
     * Dispatches restore tasks for each archive found in the database.
     *
     * @param  array<string>  $tables  Optional list of table names to filter by
     */
    public function restoreFromModels(array $tables = []): void;

    /**
     * Restores models from backup files in storage.
     *
     * Dispatches restore tasks for each backup file found in the configured folder.
     *
     * @param  array<string>  $tables  Optional list of table names to filter by
     */
    public function restoreFromFiles(array $tables = []): void;

    /**
     * Registers observers for all archivable models.
     *
     * Attaches the ArchivableObserver to each model listed in the configuration.
     * This enables automatic archiving on model events.
     */
    public function registerObservers(): void;
}

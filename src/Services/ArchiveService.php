<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Services;

use AndyDefer\ConsoleWriter\Utils\ProgressManager;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Contracts\Services\ArchiveServiceInterface;
use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Observers\ArchivableObserver;
use AndyDefer\LaravelToth\Records\ArchiveFiltersRecord;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\LaravelToth\Tasks\BackupArchiveTask;
use AndyDefer\LaravelToth\Tasks\RestoreArchiveTask;
use AndyDefer\LaravelToth\Tasks\UpdateOrCreateArchiveTask;
use AndyDefer\LaravelToth\Tasks\UpdateOrCreateFromFileTask;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Models\UniqueTask;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/**
 * Service responsible for managing archives, backups, and restorations.
 */
final class ArchiveService implements ArchiveServiceInterface
{
    private bool $mute = false;

    public function __construct(
        private readonly TothConfigInterface $config,
        private readonly UniqueTaskServiceInterface $taskService,
        private readonly ArchiveRepository $archiveRepository,
        private readonly ProgressManager $progress,
    ) {}

    /** {@inheritDoc} */
    public function setMute(bool $mute): self
    {
        $this->mute = $mute;

        return $this;
    }

    /** {@inheritDoc} */
    public function isMuted(): bool
    {
        return $this->mute;
    }

    /** {@inheritDoc} */
    public function createOrUpdateArchive(Model $model): ?Archive
    {
        $this->cancelPendingArchiveTask($model);
        $this->dispatchArchiveCreationTask($model);

        $filters = ArchiveFiltersRecord::from([
            'table_name' => $model->getTable(),
            'row_id' => $model->getKey(),
            'model_class' => get_class($model),
        ]);

        return $this->archiveRepository->findBy(
            FindByRecord::from(['filters' => $filters])
        )->first();
    }

    /** {@inheritDoc} */
    public function backup(Archive $archive): void
    {
        $this->cancelPendingBackupTask($archive);
        $this->dispatchBackupTask($archive);
    }

    /** {@inheritDoc} */
    public function backupFromModels(array $tables = []): void
    {
        $archivableModels = $this->config->getArchivables();
        $totalRecords = 0;

        foreach ($archivableModels as $modelClass) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass;
            $tableName = $model->getTable();

            if ($this->shouldSkipTable($tableName, $tables)) {
                continue;
            }

            $totalRecords += $modelClass::count();
        }

        if ($totalRecords === 0) {
            return;
        }

        if (! $this->mute) {
            $this->progress->start('📦 Backing up models', $totalRecords);
        }

        $processed = 0;

        foreach ($archivableModels as $modelClass) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass;
            $tableName = $model->getTable();

            if ($this->shouldSkipTable($tableName, $tables)) {
                continue;
            }

            $records = $modelClass::all();

            foreach ($records as $record) {
                $this->createOrUpdateArchive($record);
                $processed++;
                if (! $this->mute) {
                    $this->progress->update($processed, "📦 {$tableName}");
                }
            }
        }

        if (! $this->mute) {
            $this->progress->finish('✅ Backup completed');
        }
    }

    /** {@inheritDoc} */
    public function backupFromFiles(array $tables = []): void
    {
        $backupPath = $this->config->getBackupFolderPath();

        if (! File::exists($backupPath)) {
            return;
        }

        $directories = File::directories($backupPath);
        $totalFiles = 0;

        foreach ($directories as $directory) {
            $tableName = basename($directory);

            if ($this->shouldSkipTable($tableName, $tables)) {
                continue;
            }

            $files = File::files($directory);
            $totalFiles += count($files);
        }

        if ($totalFiles === 0) {
            return;
        }

        if (! $this->mute) {
            $this->progress->start('📂 Restoring from files', $totalFiles);
        }

        $processed = 0;

        foreach ($directories as $directory) {
            $tableName = basename($directory);

            if ($this->shouldSkipTable($tableName, $tables)) {
                continue;
            }

            $files = File::files($directory);

            foreach ($files as $file) {
                $rowId = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $this->dispatchFileBasedArchiveTask($tableName, $rowId);
                $processed++;
                if (! $this->mute) {
                    $this->progress->update($processed, "📄 {$tableName}:{$rowId}");
                }
            }
        }

        if (! $this->mute) {
            $this->progress->finish('✅ Files restored');
        }
    }

    /** {@inheritDoc} */
    public function restoreFromModels(array $tables = []): void
    {
        $archivableModels = $this->config->getArchivables();
        $totalArchives = 0;

        foreach ($archivableModels as $modelClass) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass;
            $tableName = $model->getTable();

            if ($this->shouldSkipTable($tableName, $tables)) {
                continue;
            }

            $archives = $this->archiveRepository->findBy(
                FindByRecord::from([
                    'filters' => ArchiveFiltersRecord::from([
                        'table_name' => $tableName,
                    ]),
                ])
            );

            $totalArchives += $archives->count();
        }

        if ($totalArchives === 0) {
            return;
        }

        if (! $this->mute) {
            $this->progress->start('🔄 Restoring from database', $totalArchives);
        }

        $processed = 0;

        foreach ($archivableModels as $modelClass) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass;
            $tableName = $model->getTable();

            if ($this->shouldSkipTable($tableName, $tables)) {
                continue;
            }

            $archives = $this->archiveRepository->findBy(
                FindByRecord::from([
                    'filters' => ArchiveFiltersRecord::from([
                        'table_name' => $tableName,
                    ]),
                ])
            );

            foreach ($archives as $archive) {
                $this->dispatchRestorationTask($archive->table_name, $archive->row_id);
                $processed++;
                if (! $this->mute) {
                    $this->progress->update($processed, "♻️ {$archive->table_name}:{$archive->row_id}");
                }
            }
        }

        if (! $this->mute) {
            $this->progress->finish('✅ Restore completed');
        }
    }

    /** {@inheritDoc} */
    public function restoreFromFiles(array $tables = []): void
    {
        $backupPath = $this->config->getBackupFolderPath();

        if (! File::exists($backupPath)) {
            return;
        }

        $directories = File::directories($backupPath);
        $totalFiles = 0;

        foreach ($directories as $directory) {
            $tableName = basename($directory);

            if ($this->shouldSkipTable($tableName, $tables)) {
                continue;
            }

            $files = File::files($directory);
            $totalFiles += count($files);
        }

        if ($totalFiles === 0) {
            return;
        }

        if (! $this->mute) {
            $this->progress->start('📂 Restoring from files', $totalFiles);
        }

        $processed = 0;

        foreach ($directories as $directory) {
            $tableName = basename($directory);

            if ($this->shouldSkipTable($tableName, $tables)) {
                continue;
            }

            $files = File::files($directory);

            foreach ($files as $file) {
                $rowId = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $this->dispatchRestorationTask($tableName, $rowId);
                $processed++;
                if (! $this->mute) {
                    $this->progress->update($processed, "📄 {$tableName}:{$rowId}");
                }
            }
        }

        if (! $this->mute) {
            $this->progress->finish('✅ Restore completed');
        }
    }

    /** {@inheritDoc} */
    public function registerObservers(): void
    {
        $archivableModels = $this->config->getArchivables();

        foreach ($archivableModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::observe(ArchivableObserver::class);
            }
        }
    }

    /**
     * Cancels any pending archive creation task for the given model.
     */
    private function cancelPendingArchiveTask(Model $model): void
    {
        $task = UniqueTask::whereIn('status', ['pending', 'in_progress'])
            ->where('fqcn', UpdateOrCreateArchiveTask::class)
            ->where('payload->model_class', get_class($model))
            ->where('payload->model_id', $model->getKey())
            ->first();

        if ($task) {
            $this->taskService->cancel(
                $task->getAlias(),
                new DescriptionVO('Newer modification detected, cancelling old task')
            );
        }
    }

    /**
     * Cancels any pending backup task for the given archive.
     */
    private function cancelPendingBackupTask(Archive $archive): void
    {
        $task = UniqueTask::whereIn('status', ['pending', 'in_progress'])
            ->where('fqcn', BackupArchiveTask::class)
            ->where('payload->archive_id', $archive->id)
            ->first();

        if ($task) {
            $this->taskService->cancel(
                $task->getAlias(),
                new DescriptionVO('Newer backup requested, cancelling old task')
            );
        }
    }

    /**
     * Dispatches a task to create or update an archive for a model.
     */
    private function dispatchArchiveCreationTask(Model $model): void
    {
        $payload = StrictDataObject::from([
            'model_class' => get_class($model),
            'model_id' => $model->getKey(),
        ]);

        $this->taskService->register(
            new UniqueTaskFqcnVO(UpdateOrCreateArchiveTask::class),
            $payload,
            $this->createTaskConfig('Update or create archive task')
        );
    }

    /**
     * Dispatches a task to create a backup file for an archive.
     */
    private function dispatchBackupTask(Archive $archive): void
    {
        $payload = StrictDataObject::from([
            'archive_id' => $archive->id,
        ]);

        $this->taskService->register(
            new UniqueTaskFqcnVO(BackupArchiveTask::class),
            $payload,
            $this->createTaskConfig('Backup archive task')
        );
    }

    /**
     * Dispatches a task to restore a model from an archive.
     */
    private function dispatchRestorationTask(string $tableName, string $rowId): void
    {
        $payload = StrictDataObject::from([
            'table_name' => $tableName,
            'row_id' => $rowId,
        ]);

        $this->taskService->register(
            new UniqueTaskFqcnVO(RestoreArchiveTask::class),
            $payload,
            $this->createTaskConfig('Restore archive task')
        );
    }

    /**
     * Dispatches a task to create an archive from a backup file.
     */
    private function dispatchFileBasedArchiveTask(string $tableName, string $rowId): void
    {
        $payload = StrictDataObject::from([
            'table_name' => $tableName,
            'row_id' => $rowId,
        ]);

        $this->taskService->register(
            new UniqueTaskFqcnVO(UpdateOrCreateFromFileTask::class),
            $payload,
            $this->createTaskConfig('Update or create archive from file task')
        );
    }

    /**
     * Determines whether a table should be skipped based on the filter.
     */
    private function shouldSkipTable(string $tableName, array $allowedTables): bool
    {
        return ! empty($allowedTables) && ! in_array($tableName, $allowedTables, true);
    }

    /**
     * Creates a task configuration with values from the package configuration.
     */
    private function createTaskConfig(string $description): UniqueTaskConfigRecord
    {
        return UniqueTaskConfigRecord::from([
            'scheduled_at' => now()->addSeconds($this->config->getTaskDelaySeconds())->toIso8601String(),
            'max_attempts' => $this->config->getMaxAttempts(),
            'grace_period' => $this->config->getGracePeriodSeconds(),
            'description' => $description,
        ]);
    }
}

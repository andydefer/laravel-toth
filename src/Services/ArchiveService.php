<?php

// src/Services/ArchiveService.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Services;

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
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Models\UniqueTask;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

final class ArchiveService implements ArchiveServiceInterface
{
    public function __construct(
        private readonly TothConfigInterface $config,
        private readonly UniqueTaskServiceInterface $taskService,
        private readonly ArchiveRepository $archiveRepository
    ) {}

    public function createOrUpdateArchive(Model $model): ?Archive
    {
        $this->cancelExistingTask($model);
        $this->dispatchUpdateOrCreateTask($model);

        $filters = ArchiveFiltersRecord::from([
            'table_name' => $model->getTable(),
            'row_id' => $model->getKey(),
            'model_class' => get_class($model),
        ]);

        return $this->archiveRepository->findBy(
            FindByRecord::from([
                'filters' => $filters,
            ])
        )->first();
    }

    public function backup(Archive $archive): void
    {
        $this->cancelExistingBackupTask($archive);
        $this->dispatchBackupTask($archive);
    }

    public function backupFromModels(array $tables = []): void
    {
        $archivables = $this->config->getArchivables();

        foreach ($archivables as $modelClass) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass;
            $tableName = $model->getTable();

            if (! empty($tables) && ! in_array($tableName, $tables)) {
                continue;
            }

            $records = $modelClass::all();

            foreach ($records as $record) {
                $this->createOrUpdateArchive($record);
            }
        }
    }

    public function backupFromFiles(array $tables = []): void
    {
        $backupPath = $this->config->getBackupFolderPath();

        if (! File::exists($backupPath)) {
            return;
        }

        $directories = File::directories($backupPath);

        foreach ($directories as $directory) {
            $tableName = basename($directory);

            if (! empty($tables) && ! in_array($tableName, $tables)) {
                continue;
            }

            $files = File::files($directory);

            foreach ($files as $file) {
                $rowId = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $this->createOrUpdateArchiveFromFile($tableName, $rowId);
            }
        }
    }

    public function restoreFromModels(array $tables = []): void
    {
        $archivables = $this->config->getArchivables();

        foreach ($archivables as $modelClass) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass;
            $tableName = $model->getTable();

            if (! empty($tables) && ! in_array($tableName, $tables)) {
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
                $this->dispatchRestoreTask(
                    $archive->table_name,
                    $archive->row_id
                );
            }
        }
    }

    public function restoreFromFiles(array $tables = []): void
    {
        $backupPath = $this->config->getBackupFolderPath();

        if (! File::exists($backupPath)) {
            return;
        }

        $directories = File::directories($backupPath);

        foreach ($directories as $directory) {
            $tableName = basename($directory);

            if (! empty($tables) && ! in_array($tableName, $tables)) {
                continue;
            }

            $files = File::files($directory);

            foreach ($files as $file) {
                $rowId = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $this->dispatchRestoreTask($tableName, $rowId);
            }
        }
    }

    public function registerObservers(): void
    {
        $archivables = $this->config->getArchivables();

        foreach ($archivables as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::observe(ArchivableObserver::class);
            }
        }
    }

    private function createOrUpdateArchiveFromFile(string $tableName, string $rowId): void
    {
        $backupPath = $this->config->getBackupFolderPath();
        $filePath = $backupPath.'/'.$tableName.'/'.$rowId.'.php';

        if (! File::exists($filePath)) {
            return;
        }

        $data = require $filePath;

        if (empty($data)) {
            return;
        }

        Archive::updateOrCreate(
            [
                'table_name' => $tableName,
                'row_id' => $rowId,
            ],
            [
                'data' => $data,
                'last_save_at' => now(),
            ]
        );
    }

    protected function cancelExistingTask(Model $model): void
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

    protected function cancelExistingBackupTask(Archive $archive): void
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

    private function dispatchUpdateOrCreateTask(Model $model): void
    {
        $payload = StrictDataObject::from([
            'model_class' => get_class($model),
            'model_id' => $model->getKey(),
        ]);

        $this->taskService->register(
            new UniqueTaskFqcnVO(UpdateOrCreateArchiveTask::class),
            $payload,
            UniqueTaskConfigRecord::from([
                'scheduled_at' => now()->addSeconds(5)->toIso8601String(),
                'max_attempts' => 3,
                'grace_period' => 60,
            ])
        );
    }

    private function dispatchBackupTask(Archive $archive): void
    {
        $payload = StrictDataObject::from([
            'archive_id' => $archive->id,
        ]);

        $this->taskService->register(
            new UniqueTaskFqcnVO(BackupArchiveTask::class),
            $payload,
            UniqueTaskConfigRecord::from([
                'scheduled_at' => now()->addSeconds(5)->toIso8601String(),
                'max_attempts' => 3,
                'grace_period' => 60,
            ])
        );
    }

    private function dispatchRestoreTask(string $tableName, string $rowId): void
    {
        $payload = StrictDataObject::from([
            'table_name' => $tableName,
            'row_id' => $rowId,
        ]);

        $this->taskService->register(
            new UniqueTaskFqcnVO(RestoreArchiveTask::class),
            $payload,
            UniqueTaskConfigRecord::from([
                'scheduled_at' => now()->addSeconds(5)->toIso8601String(),
                'max_attempts' => 3,
                'grace_period' => 60,
            ])
        );
    }
}

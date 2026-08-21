<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tasks;

use AndyDefer\LaravelToth\Helpers\BackupFileHelper;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\Task\Abstract\AbstractUniqueTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use RuntimeException;

/**
 * Task that creates or updates an archive for a model and generates a backup file.
 *
 * This task is dispatched asynchronously to capture the current state of a model
 * in both the archive table and as a physical backup file. It handles the creation
 * of new archives or updates to existing ones.
 */
final class UpdateOrCreateArchiveTask extends AbstractUniqueTask
{
    protected function process(): void
    {
        $payload = $this->context->getPayload();

        $modelClass = $payload->model_class;
        $modelId = $payload->model_id;

        $model = $modelClass::find($modelId);

        if (! $model) {
            $this->error(new DescriptionVO("Model not found: {$modelClass} with ID {$modelId}"));
            throw new RuntimeException("Model not found: {$modelClass} with ID {$modelId}");
        }

        $archiveRepository = $this->context->getLaravelApp()->make(ArchiveRepository::class);
        $backupHelper = $this->context->getLaravelApp()->make(BackupFileHelper::class);

        $archive = $archiveRepository->updateOrCreate(
            [
                'table_name' => $model->getTable(),
                'row_id' => $model->getKey(),
                'model_class' => get_class($model),
            ],
            [
                'data' => $model->toArray(),
                'last_save_at' => now()->toIso8601String(),
            ]
        );

        $backupHelper->createBackupFile($archive);

        $this->info(new DescriptionVO("Archive updated for {$modelClass}:{$modelId} with backup"));
    }
}

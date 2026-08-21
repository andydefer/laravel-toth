<?php

// src/Tasks/UpdateOrCreateArchiveTask.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tasks;

use AndyDefer\LaravelToth\Records\ArchiveRecord;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\Task\Abstract\AbstractUniqueTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;

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

            return;
        }

        $archiveRepository = $this->context->getLaravelApp()->make(ArchiveRepository::class);

        $archiveRepository->create(ArchiveRecord::from([
            'table_name' => $model->getTable(),
            'row_id' => $model->getKey(),
            'model_class' => get_class($model),
            'data' => $model->toArray(),
            'last_save_at' => now()->toIso8601String(),
        ]));

        $this->info(new DescriptionVO("Archive created/updated for {$modelClass}:{$modelId}"));
    }
}

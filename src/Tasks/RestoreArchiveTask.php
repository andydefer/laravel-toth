<?php

// src/Tasks/RestoreArchiveTask.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tasks;

use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Records\ArchiveFiltersRecord;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;
use AndyDefer\Task\Abstract\AbstractUniqueTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final class RestoreArchiveTask extends AbstractUniqueTask
{
    protected function process(): void
    {
        $payload = $this->context->getPayload();

        $tableName = $payload->table_name;
        $rowId = $payload->row_id;

        $archiveRepository = $this->context->getLaravelApp()->make(ArchiveRepository::class);
        $config = $this->context->getLaravelApp()->make(TothConfigInterface::class);

        // 1. Récupérer l'archive la plus récente en DB
        $filters = ArchiveFiltersRecord::from([
            'table_name' => $tableName,
            'row_id' => $rowId,
        ]);

        $archive = $archiveRepository->findBy(
            new FindByRecord(
                filters: $filters,
                sortBy: new SortColumns('last_save_at:desc')
            )
        )->first();

        // 2. Récupérer les données du backup dans le storage
        $backupData = $this->getBackupData($tableName, $rowId, $config);
        $backupTimestamp = $this->getBackupTimestamp($tableName, $rowId, $config);

        // 3. Déterminer les données les plus récentes
        $dbData = $archive ? $archive->data->toArray() : null;
        $dbTimestamp = $archive ? strtotime($archive->last_save_at) : 0;

        $data = null;
        $source = null;

        if ($dbData && $backupData) {
            if ($dbTimestamp >= $backupTimestamp) {
                $data = $dbData;
                $source = 'database';
            } else {
                $data = $backupData;
                $source = 'storage';
            }
        } elseif ($dbData) {
            $data = $dbData;
            $source = 'database';
        } elseif ($backupData) {
            $data = $backupData;
            $source = 'storage';
        }

        if (! $data) {
            $this->error(new DescriptionVO("No data found to restore for {$tableName}:{$rowId}"));

            return;
        }

        // 4. Vérifier le model_class
        $modelClass = $archive ? $archive->model_class : null;

        if (! $modelClass) {
            $this->error(new DescriptionVO("No model_class found for {$tableName}:{$rowId}"));

            return;
        }

        // 5. Vérifier si le modèle existe déjà
        $existingModel = $modelClass::find($rowId);

        if ($existingModel) {
            $this->error(new DescriptionVO(
                "Cannot restore: Model {$modelClass} with ID {$rowId} already exists in database"
            ));

            return;
        }

        // 6. Désactiver les contraintes de clés étrangères
        $this->disableForeignKeyChecks();

        try {
            // 7. Créer le modèle
            $model = new $modelClass;
            $model->fill($data);
            $model->save();

            $this->info(new DescriptionVO(
                "Model {$modelClass} with ID {$rowId} restored successfully from {$source}"
            ));
        } finally {
            // 8. Réactiver les contraintes de clés étrangères
            $this->enableForeignKeyChecks();
        }
    }

    private function getBackupData(string $tableName, string $rowId, TothConfigInterface $config): ?array
    {
        $backupPath = $config->getBackupFolderPath();
        $filePath = $backupPath.'/'.$tableName.'/'.$rowId.'.php';

        if (! File::exists($filePath)) {
            return null;
        }

        return require $filePath;
    }

    private function getBackupTimestamp(string $tableName, string $rowId, TothConfigInterface $config): int
    {
        $backupPath = $config->getBackupFolderPath();
        $filePath = $backupPath.'/'.$tableName.'/'.$rowId.'.php';

        if (! File::exists($filePath)) {
            return 0;
        }

        return File::lastModified($filePath);
    }

    private function disableForeignKeyChecks(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'pgsql') {
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }
    }

    private function enableForeignKeyChecks(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'pgsql') {
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }
}

<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tasks;

use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Records\ArchiveFiltersRecord;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;
use AndyDefer\Task\Abstract\AbstractUniqueTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Task that restores a model from an archive or backup file.
 *
 * This task retrieves the most recent data from either the database archive
 * or the backup file (whichever is newer), and restores it to the original
 * model with its original ID. Foreign key constraints are temporarily disabled
 * during restoration to avoid integrity violations.
 */
final class RestoreArchiveTask extends AbstractUniqueTask
{
    protected function process(): void
    {
        $payload = $this->context->getPayload();
        $tableName = $payload->table_name;
        $rowId = $payload->row_id;

        $archiveRepository = $this->context->getLaravelApp()->make(ArchiveRepository::class);
        $config = $this->context->getLaravelApp()->make(TothConfigInterface::class);

        $archive = $this->findLatestArchive($archiveRepository, $tableName, $rowId);
        $backupData = $this->getBackupData($tableName, $rowId, $config);
        $backupTimestamp = $this->getBackupTimestamp($tableName, $rowId, $config);

        $restorationData = $this->determineRestorationSource($archive, $backupData, $backupTimestamp);

        if (! $restorationData) {
            $this->error(new DescriptionVO("No data found to restore for {$tableName}:{$rowId}"));
            throw new RuntimeException("No data found to restore for {$tableName}:{$rowId}");
        }

        $modelClass = $this->resolveModelClass($archive, $tableName, $config);

        if (! $modelClass) {
            $this->error(new DescriptionVO("No model_class found for {$tableName}:{$rowId}"));
            throw new RuntimeException("No model_class found for {$tableName}:{$rowId}");
        }

        $this->ensureModelDoesNotExist($modelClass, $rowId, $tableName);

        $this->restoreModel($modelClass, $rowId, $restorationData['data'], $restorationData['source']);
    }

    /**
     * Finds the most recent archive for a given table and row ID.
     */
    private function findLatestArchive(ArchiveRepository $repository, string $tableName, string $rowId): mixed
    {
        $filters = ArchiveFiltersRecord::from([
            'table_name' => $tableName,
            'row_id' => $rowId,
        ]);

        return $repository->findBy(
            new FindByRecord(
                filters: $filters,
                sortBy: new SortColumns('last_save_at:desc')
            )
        )->first();
    }

    /**
     * Determines whether to use database data or backup file data for restoration.
     *
     * @return array{data: array, source: string}|null
     */
    private function determineRestorationSource(?object $archive, ?array $backupData, int $backupTimestamp): ?array
    {
        $dbData = $archive ? $archive->data->toArray() : null;
        $dbTimestamp = $archive ? $archive->last_save_at->timestamp : 0;

        if ($dbData && $backupData) {
            return $dbTimestamp >= $backupTimestamp
                ? ['data' => $dbData, 'source' => 'database']
                : ['data' => $backupData, 'source' => 'storage'];
        }

        if ($dbData) {
            return ['data' => $dbData, 'source' => 'database'];
        }

        if ($backupData) {
            return ['data' => $backupData, 'source' => 'storage'];
        }

        return null;
    }

    /**
     * Resolves the model class name from the archive or configuration.
     */
    private function resolveModelClass(?object $archive, string $tableName, TothConfigInterface $config): ?string
    {
        if ($archive && $archive->model_class) {
            return $archive->model_class;
        }

        $archivables = $config->getArchivables();

        foreach ($archivables as $class) {
            if (class_exists($class)) {
                $model = new $class;
                if ($model->getTable() === $tableName) {
                    return $class;
                }
            }
        }

        return null;
    }

    /**
     * Ensures that a model with the given ID does not already exist.
     *
     * @throws RuntimeException If the model already exists
     */
    private function ensureModelDoesNotExist(string $modelClass, string $rowId, string $tableName): void
    {
        $existingModel = $modelClass::find($rowId);

        if ($existingModel) {
            $message = "Cannot restore: Model {$modelClass} with ID {$rowId} already exists in database";
            $this->error(new DescriptionVO($message));
            throw new RuntimeException($message);
        }
    }

    /**
     * Restores a model with the given data and ID.
     */
    private function restoreModel(string $modelClass, string $rowId, array $data, string $source): void
    {
        $this->disableForeignKeyChecks();

        try {
            $model = new $modelClass;

            unset($data['id']);

            $model->fill($data);
            $model->setAttribute('id', $rowId);
            $model->save();

            $this->info(new DescriptionVO(
                "Model {$modelClass} with ID {$rowId} restored successfully from {$source}"
            ));
        } finally {
            $this->enableForeignKeyChecks();
        }
    }

    /**
     * Reads backup data from a PHP file.
     */
    private function getBackupData(string $tableName, string $rowId, TothConfigInterface $config): ?array
    {
        $backupPath = $config->getBackupFolderPath();
        $filePath = $backupPath.'/'.$tableName.'/'.$rowId.'.php';

        if (! File::exists($filePath)) {
            return null;
        }

        return require $filePath;
    }

    /**
     * Gets the last modified timestamp of a backup file.
     */
    private function getBackupTimestamp(string $tableName, string $rowId, TothConfigInterface $config): int
    {
        $backupPath = $config->getBackupFolderPath();
        $filePath = $backupPath.'/'.$tableName.'/'.$rowId.'.php';

        if (! File::exists($filePath)) {
            return 0;
        }

        return File::lastModified($filePath);
    }

    /**
     * Disables foreign key checks for the current database driver.
     */
    private function disableForeignKeyChecks(): void
    {
        $driver = DB::connection()->getDriverName();

        match ($driver) {
            'mysql' => DB::statement('SET FOREIGN_KEY_CHECKS=0'),
            'pgsql' => DB::statement('SET CONSTRAINTS ALL DEFERRED'),
            'sqlite' => DB::statement('PRAGMA foreign_keys = OFF'),
            default => null,
        };
    }

    /**
     * Re-enables foreign key checks for the current database driver.
     */
    private function enableForeignKeyChecks(): void
    {
        $driver = DB::connection()->getDriverName();

        match ($driver) {
            'mysql' => DB::statement('SET FOREIGN_KEY_CHECKS=1'),
            'pgsql' => DB::statement('SET CONSTRAINTS ALL IMMEDIATE'),
            'sqlite' => DB::statement('PRAGMA foreign_keys = ON'),
            default => null,
        };
    }
}

<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tasks;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\Task\Abstract\AbstractUniqueTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Task that creates or updates an archive from a backup file.
 *
 * This task reads a backup file from the storage directory and recreates
 * the corresponding archive entry in the database. It resolves the model
 * class from the configuration based on the table name.
 */
final class UpdateOrCreateFromFileTask extends AbstractUniqueTask
{
    protected function process(): void
    {
        $payload = $this->context->getPayload();
        $tableName = $payload->table_name;
        $rowId = $payload->row_id;

        $config = $this->context->getLaravelApp()->make(TothConfigInterface::class);
        $archiveRepository = $this->context->getLaravelApp()->make(ArchiveRepository::class);

        $filePath = $this->buildFilePath($config, $tableName, $rowId);

        $this->ensureFileExists($filePath);

        $data = $this->loadBackupData($filePath);

        $modelClass = $this->resolveModelClass($config, $tableName);

        $this->ensureModelClassFound($modelClass, $tableName);

        $archiveRepository->updateOrCreate(
            [
                'table_name' => $tableName,
                'row_id' => $rowId,
                'model_class' => $modelClass,
            ],
            [
                'data' => StrictAssociative::from($data),
                'last_save_at' => now()->toIso8601String(),
            ]
        );

        $this->info(new DescriptionVO("Archive created/updated from file for {$tableName}:{$rowId}"));
    }

    /**
     * Builds the full file path for a backup file.
     */
    private function buildFilePath(TothConfigInterface $config, string $tableName, string $rowId): string
    {
        $backupPath = $config->getBackupFolderPath();

        return $backupPath.'/'.$tableName.'/'.$rowId.'.php';
    }

    /**
     * Ensures that the backup file exists.
     *
     * @throws RuntimeException If the file does not exist
     */
    private function ensureFileExists(string $filePath): void
    {
        if (! File::exists($filePath)) {
            $this->error(new DescriptionVO("Backup file not found: {$filePath}"));
            throw new RuntimeException("Backup file not found: {$filePath}");
        }
    }

    /**
     * Loads and validates backup data from a file.
     *
     * @return array<string, mixed> The backup data
     *
     * @throws RuntimeException If the data is empty
     */
    private function loadBackupData(string $filePath): array
    {
        $data = require $filePath;

        if (empty($data)) {
            $this->error(new DescriptionVO("Backup file is empty: {$filePath}"));
            throw new RuntimeException("Backup file is empty: {$filePath}");
        }

        return $data;
    }

    /**
     * Resolves the model class name from the table name using configuration.
     */
    private function resolveModelClass(TothConfigInterface $config, string $tableName): ?string
    {
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
     * Ensures that a model class was found for the given table.
     *
     * @throws RuntimeException If no model class is found
     */
    private function ensureModelClassFound(?string $modelClass, string $tableName): void
    {
        if (! $modelClass) {
            $this->error(new DescriptionVO("Model class not found for table: {$tableName}"));
            throw new RuntimeException("Model class not found for table: {$tableName}");
        }
    }
}

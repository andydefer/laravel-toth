<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Helpers;

use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Models\Archive;
use Illuminate\Support\Facades\File;

/**
 * Helper for creating backup files from archive data.
 *
 * Responsible for writing archive data to PHP files in the configured backup directory.
 * Each backup file returns the archived data as a PHP array when included.
 */
final class BackupFileHelper
{
    public function __construct(
        private readonly TothConfigInterface $config,
    ) {}

    /**
     * Creates a PHP backup file for the given archive.
     *
     * The file is stored at: {backupPath}/{tableName}/{rowId}.php
     * It contains a PHP array representation of the archive data.
     *
     * @param  Archive  $archive  The archive to back up
     * @return string The absolute path to the created backup file
     */
    public function createBackupFile(Archive $archive): string
    {
        $filePath = $this->buildFilePath($archive);

        $this->ensureDirectoryExists($filePath);

        File::put($filePath, $this->generateFileContent($archive));

        return $filePath;
    }

    /**
     * Builds the full file path for a backup file.
     *
     * @param  Archive  $archive  The archive to back up
     * @return string Absolute path to the backup file
     */
    private function buildFilePath(Archive $archive): string
    {
        $basePath = $this->config->getBackupFolderPath();

        return $basePath.'/'.$archive->table_name.'/'.$archive->row_id.'.php';
    }

    /**
     * Ensures the target directory exists for a given file path.
     *
     * @param  string  $filePath  Absolute path to the file
     */
    private function ensureDirectoryExists(string $filePath): void
    {
        $directory = dirname($filePath);

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Generates the PHP file content for a backup file.
     *
     * @param  Archive  $archive  The archive to back up
     * @return string PHP code that returns the archived data as an array
     */
    private function generateFileContent(Archive $archive): string
    {
        $data = var_export($archive->data->toArray(), true);

        return '<?php'.PHP_EOL.PHP_EOL.'return '.$data.';'.PHP_EOL;
    }
}

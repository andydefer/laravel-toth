<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Directives;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Services\ArchiveService;

/**
 * CLI command to create backups for archivable models.
 *
 * Supports filtering by specific tables and choosing between database and file storage.
 *
 * @example
 * bin/task toth:backup                     // Backup all configured models
 * bin/task toth:backup [users,posts]       // Backup only users and posts
 * bin/task toth:backup --only-db           // Backup only from database
 * bin/task toth:backup --only-files        // Backup only from storage files
 */
final class TothBackupDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'toth:backup 
                    {tables*}#"Tables to backup (e.g., users, posts)" 
                    {--only-files}#"Backup only from storage files" 
                    {--only-db}#"Backup only from database"';
    }

    public function getDescription(): string
    {
        return 'Create backups for archivable models. Specify tables or use flags to filter.';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['backup', 'bkp']);
    }

    protected function execute(): ExitCode
    {
        $this->info('📦 Starting backup process...');

        $tables = $this->getTablesFromInput();
        $onlyFiles = $this->getFlag('only-files');
        $onlyDb = $this->getFlag('only-db');

        if ($onlyFiles && $onlyDb) {
            $this->displayMutuallyExclusiveFlagsError();

            return ExitCode::INVALID_ARGUMENT;
        }

        $archiveService = $this->getApplication()->make(ArchiveService::class);

        if ($onlyFiles) {
            $this->info('📂 Backup only from storage (files)');
            $archiveService->backupFromFiles($tables);
        } elseif ($onlyDb) {
            $this->info('💾 Backup only from database');
            $archiveService->backupFromModels($tables);
        } else {
            $this->info('💾📂 Backup from both database and storage');
            $archiveService->backupFromModels($tables);
            $archiveService->backupFromFiles($tables);
        }

        $this->newLine();
        $this->info('✅ Backup process completed');

        return ExitCode::SUCCESS;
    }

    private function getTablesFromInput(): array
    {
        $tables = $this->getVariadic('tables');

        if (! empty($tables)) {
            return $tables;
        }

        $config = $this->getApplication()->make(TothConfigInterface::class);
        $archivables = $config->getArchivables();

        foreach ($archivables as $modelClass) {
            if (class_exists($modelClass)) {
                $model = new $modelClass;
                $tables[] = $model->getTable();
            }
        }

        $this->info('📋 No tables specified, using all archivable models from config');

        return $tables;
    }

    private function displayMutuallyExclusiveFlagsError(): void
    {
        $this->getConsole()->error('❌ You cannot use --only-files and --only-db at the same time');
        $this->getConsole()->line();

        $this->getConsole()->raw(KeyValue::renderWithValueColor(
            MapCollection::from([
                'Error' => 'Mutually exclusive flags',
                '--only-files' => 'Backup only from storage files',
                '--only-db' => 'Backup only from database',
                'Solution' => 'Use only one flag or none',
            ]),
            'red'
        ));
    }
}

<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Directives;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Contracts\Services\ArchiveServiceInterface;

/**
 * CLI command to restore data from archives.
 *
 * Supports filtering by specific tables and choosing between database and file storage.
 *
 * @example
 * bin/task toth:restore                     // Restore all configured models
 * bin/task toth:restore [users,posts]       // Restore only users and posts
 * bin/task toth:restore --only-db           // Restore only from database
 * bin/task toth:restore --only-files        // Restore only from storage files
 * bin/task toth:restore --mute              // Restore without progress bars
 */
final class TothRestoreDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'toth:restore 
                    {tables*}#"Tables to restore (e.g., users, posts)" 
                    {--only-files}#"Restore only from storage files" 
                    {--only-db}#"Restore only from database"
                    {--mute}#"Disable progress bars"';
    }

    public function getDescription(): string
    {
        return 'Restore data from archives. Specify tables or use flags to filter.';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['restore', 'rst']);
    }

    protected function execute(): ExitCode
    {
        $this->info('🔄 Starting restore process...');

        $tables = $this->getTablesFromInput();
        $onlyFiles = $this->getFlag('only-files');
        $onlyDb = $this->getFlag('only-db');
        $mute = $this->getFlag('mute');

        if ($onlyFiles && $onlyDb) {
            $this->displayMutuallyExclusiveFlagsError();

            return ExitCode::INVALID_ARGUMENT;
        }

        /** @var ArchiveServiceInterface $archiveService */
        $archiveService = $this->getApplication()->make(ArchiveServiceInterface::class);

        $archiveService->setMute($mute);

        if ($onlyFiles) {
            $this->info('📂 Restore only from storage (files)');
            $archiveService->restoreFromFiles($tables);
        } elseif ($onlyDb) {
            $this->info('💾 Restore only from database');
            $archiveService->restoreFromModels($tables);
        } else {
            $this->info('💾📂 Restore from both database and storage');
            $archiveService->restoreFromModels($tables);
            $archiveService->restoreFromFiles($tables);
        }

        $this->newLine();
        $this->info('✅ Restore process completed');

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
                '--only-files' => 'Restore only from storage files',
                '--only-db' => 'Restore only from database',
                'Solution' => 'Use only one flag or none',
            ]),
            'red'
        ));
    }
}

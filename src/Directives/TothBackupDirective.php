<?php

// src/Directives/TothBackupDirective.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\LaravelToth\Services\ArchiveService;

final class TothBackupDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'toth:backup {tables*} {--only-table} {--only-db}';
    }

    public function getDescription(): string
    {
        return 'Create backups for archivable models. Specify tables or use flags to filter';
    }

    protected function execute(): ExitCode
    {
        $this->info('📦 Starting backup process...');

        $tables = $this->getVariadic('tables');
        $onlyTable = $this->getFlag('only-table');
        $onlyDb = $this->getFlag('only-db');

        $archiveService = $this->getApplication()->make(ArchiveService::class);

        if ($onlyTable) {
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
}

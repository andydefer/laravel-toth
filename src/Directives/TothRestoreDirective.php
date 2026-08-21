<?php

// src/Directives/TothRestoreDirective.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\LaravelToth\Services\ArchiveService;

final class TothRestoreDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'toth:restore {tables*} {--only-table} {--only-db}';
    }

    public function getDescription(): string
    {
        return 'Restore data from archives. Specify tables or use flags to filter';
    }

    protected function execute(): ExitCode
    {
        $this->info('🔄 Starting restore process...');

        $tables = $this->getVariadic('tables');
        $onlyTable = $this->getFlag('only-table');
        $onlyDb = $this->getFlag('only-db');

        $archiveService = $this->getApplication()->make(ArchiveService::class);

        if ($onlyTable) {
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
}

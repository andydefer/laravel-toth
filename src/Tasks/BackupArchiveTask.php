<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tasks;

use AndyDefer\LaravelToth\Helpers\BackupFileHelper;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\Task\Abstract\AbstractUniqueTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use RuntimeException;

/**
 * Task that creates a physical backup file for a given archive.
 *
 * This task is dispatched asynchronously to generate a PHP file containing
 * the archived data. The backup file is stored in the configured backup
 * directory and can be used for restoration purposes.
 */
final class BackupArchiveTask extends AbstractUniqueTask
{
    protected function process(): void
    {
        $payload = $this->context->getPayload();
        $archiveId = $payload->archive_id;

        $archiveRepository = $this->context->getLaravelApp()->make(ArchiveRepository::class);
        $backupHelper = $this->context->getLaravelApp()->make(BackupFileHelper::class);

        $archive = $archiveRepository->find($archiveId);

        if (! $archive) {
            $this->error(new DescriptionVO("Archive not found: ID {$archiveId}"));
            throw new RuntimeException("Archive not found: ID {$archiveId}");
        }

        $filePath = $backupHelper->createBackupFile($archive);

        $this->info(new DescriptionVO("Backup created for archive {$archiveId} at {$filePath}"));
    }
}

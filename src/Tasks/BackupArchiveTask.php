<?php

// src/Tasks/BackupArchiveTask.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tasks;

use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\Task\Abstract\AbstractUniqueTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use Illuminate\Support\Facades\File;

final class BackupArchiveTask extends AbstractUniqueTask
{
    protected function process(): void
    {
        $payload = $this->context->getPayload();
        $archiveId = $payload->archive_id;

        $archiveRepository = $this->context->getLaravelApp()->make(ArchiveRepository::class);
        $config = $this->context->getLaravelApp()->make(TothConfigInterface::class);

        $archive = $archiveRepository->find($archiveId);

        if (! $archive) {
            $this->error(new DescriptionVO("Archive not found: ID {$archiveId}"));

            return;
        }

        $backupPath = $config->getBackupFolderPath();
        $tableName = $archive->table_name;
        $rowId = $archive->row_id;

        $directory = $backupPath.'/'.$tableName;

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePath = $directory.'/'.$rowId.'.php';

        $content = '<?php'.PHP_EOL.PHP_EOL;
        $content .= 'return '.var_export($archive->data, true).';'.PHP_EOL;

        File::put($filePath, $content);

        $this->info(new DescriptionVO("Backup created for archive {$archiveId} at {$filePath}"));
    }
}

<?php

// src/Configs/TothConfig.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Configs;

use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class TothConfig implements TothConfigInterface
{
    private const DEFAULT_ARCHIVABLES = [];

    private const DEFAULT_BACKUP_FOLDER_PATH = 'toth/backups';

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getArchivables(): array
    {
        return $this->config->get(
            'toth.archivables',
            self::DEFAULT_ARCHIVABLES
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getBackupFolderPath(): string
    {
        return $this->config->get(
            'toth.backup_folder_path',
            self::DEFAULT_BACKUP_FOLDER_PATH
        );
    }
}

<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Configs;

use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Configuration manager for the Toth backup package.
 *
 * Provides access to package configuration values stored in Laravel's config system.
 * All configuration is read-only and retrieved from the 'toth.php' config file.
 */
final class TothConfig implements TothConfigInterface
{
    private const DEFAULT_ARCHIVABLES = [];

    private const DEFAULT_BACKUP_FOLDER_PATH = 'toth/backups';

    private const DEFAULT_TASK_DELAY_SECONDS = 5;

    private const DEFAULT_MAX_ATTEMPTS = 3;

    private const DEFAULT_GRACE_PERIOD_SECONDS = 60;

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function getArchivables(): array
    {
        return $this->config->get('toth.archivables', self::DEFAULT_ARCHIVABLES);
    }

    public function getBackupFolderPath(): string
    {
        return $this->config->get('toth.backup_folder_path', self::DEFAULT_BACKUP_FOLDER_PATH);
    }

    public function getTaskDelaySeconds(): int
    {
        return (int) $this->config->get('toth.task_delay_seconds', self::DEFAULT_TASK_DELAY_SECONDS);
    }

    public function getMaxAttempts(): int
    {
        return (int) $this->config->get('toth.max_attempts', self::DEFAULT_MAX_ATTEMPTS);
    }

    public function getGracePeriodSeconds(): int
    {
        return (int) $this->config->get('toth.grace_period_seconds', self::DEFAULT_GRACE_PERIOD_SECONDS);
    }
}

<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Contracts\Configs;

/**
 * Contract for the Toth backup package configuration.
 *
 * Defines the read-only interface for accessing package configuration values
 * such as archivable models, storage paths, and task execution parameters.
 */
interface TothConfigInterface
{
    /**
     * Returns the list of fully qualified class names of models to archive.
     *
     * @return array<int, string> List of model FQCNs (e.g., ['App\\Models\\User::class'])
     */
    public function getArchivables(): array;

    /**
     * Returns the base path where backup files are stored.
     *
     * @return string Absolute or relative path to the backup directory
     */
    public function getBackupFolderPath(): string;

    /**
     * Returns the delay in seconds before a task is executed.
     *
     * This delay allows for cancellation of duplicate tasks when multiple
     * updates occur in quick succession.
     *
     * @return int Delay in seconds (default: 5)
     */
    public function getTaskDelaySeconds(): int;

    /**
     * Returns the maximum number of attempts for a task before it fails.
     *
     * @return int Maximum attempts (default: 3)
     */
    public function getMaxAttempts(): int;

    /**
     * Returns the grace period in seconds before a task is considered expired.
     *
     * @return int Grace period in seconds (default: 60)
     */
    public function getGracePeriodSeconds(): int;
}

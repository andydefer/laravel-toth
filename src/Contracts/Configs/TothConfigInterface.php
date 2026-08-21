<?php

// src/Contracts/Configs/TothConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Contracts\Configs;

interface TothConfigInterface
{
    /**
     * Get the list of archivable model FQCNs.
     *
     * @return array<int, string> List of model class names
     */
    public function getArchivables(): array;

    /**
     * Get the backup folder path.
     *
     * @return string The backup folder path
     */
    public function getBackupFolderPath(): string;
}

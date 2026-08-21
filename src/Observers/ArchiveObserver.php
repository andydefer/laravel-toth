<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Observers;

use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Services\ArchiveService;

/**
 * Observer that creates a physical backup file when an archive is deleted.
 *
 * This observer ensures that a file backup is created before an archive entry
 * is removed from the database, preserving the data for potential restoration.
 */
final class ArchiveObserver
{
    public function __construct(
        private readonly ArchiveService $archiveService,
    ) {}

    /**
     * Handles the "deleted" event for an Archive model.
     *
     * Creates a physical backup file before the archive is permanently removed.
     *
     * @param  Archive  $archive  The archive being deleted
     */
    public function deleted(Archive $archive): void
    {
        $this->archiveService->backup($archive);
    }
}

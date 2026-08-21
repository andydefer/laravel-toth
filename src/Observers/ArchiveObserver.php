<?php

// src/Observers/ArchiveObserver.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Observers;

use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Services\ArchiveService;

final class ArchiveObserver
{
    public function __construct(
        private readonly ArchiveService $archiveService
    ) {}

    public function deleted(Archive $archive): void
    {
        $this->archiveService->backup($archive);
    }
}

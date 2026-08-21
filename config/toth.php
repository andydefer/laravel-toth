<?php

// config/toth.php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Archivable Models
    |--------------------------------------------------------------------------
    |
    | List of model FQCNs that should be automatically archived.
    | Add any model you want to track changes for.
    |
    */
    'archivables' => [],

    /*
    |--------------------------------------------------------------------------
    | Backup Folder Path
    |--------------------------------------------------------------------------
    |
    | The path where archive backups will be stored.
    |
    */
    'backup_folder_path' => storage_path('toth/backups'),
];

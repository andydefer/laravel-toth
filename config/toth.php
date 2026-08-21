<?php

// config/toth.php

declare(strict_types=1);
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestUser;

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
    'archivables' => [
        TestProduct::class,
        TestUser::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Folder Path
    |--------------------------------------------------------------------------
    |
    | The path where archive backups will be stored.
    |
    */
    'backup_folder_path' => storage_path('toth/backups'),

    /*
    |--------------------------------------------------------------------------
    | Task Delay Seconds
    |--------------------------------------------------------------------------
    |
    | Delay in seconds before a task is executed. This delay allows for
    | cancellation of duplicate tasks when multiple updates occur in quick
    | succession.
    |
    */
    'task_delay_seconds' => 5,

    /*
    |--------------------------------------------------------------------------
    | Max Attempts
    |--------------------------------------------------------------------------
    |
    | Maximum number of attempts for a task before it is marked as failed.
    |
    */
    'max_attempts' => 3,

    /*
    |--------------------------------------------------------------------------
    | Grace Period Seconds
    |--------------------------------------------------------------------------
    |
    | Grace period in seconds before a task is considered expired and can be
    | cleaned up.
    |
    */
    'grace_period_seconds' => 60,
];

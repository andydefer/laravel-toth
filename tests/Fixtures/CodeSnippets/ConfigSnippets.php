<?php

// tests/Fixtures/CodeSnippets/ConfigSnippets.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Fixtures\CodeSnippets;

final class ConfigSnippets
{
    public const DEFAULT_CONFIG = <<<'PHP'
<?php

return [
    'archivables' => [],
    'backup_folder_path' => storage_path('toth/backups'),
    'task_delay_seconds' => 5,
    'max_attempts' => 3,
    'grace_period_seconds' => 60,
];
PHP;

    public const CONFIG_WITH_MODELS = <<<'PHP'
<?php

return [
    'archivables' => [
        App\Models\User::class,
        App\Models\Post::class,
    ],
    'backup_folder_path' => storage_path('toth/backups'),
    'task_delay_seconds' => 5,
    'max_attempts' => 3,
    'grace_period_seconds' => 60,
];
PHP;

    public const CONFIG_WITH_CUSTOM_FOLDER = <<<'PHP'
<?php

return [
    'archivables' => [],
    'backup_folder_path' => storage_path('custom/backup/path'),
    'task_delay_seconds' => 10,
    'max_attempts' => 5,
    'grace_period_seconds' => 120,
];
PHP;

    public const CONFIG_WITHOUT_ARCHIVABLES = <<<'PHP'
<?php

return [
    'backup_folder_path' => storage_path('toth/backups'),
    'task_delay_seconds' => 5,
    'max_attempts' => 3,
    'grace_period_seconds' => 60,
];
PHP;

    public const CONFIG_WITH_TASK_DELAY = <<<'PHP'
<?php

return [
    'archivables' => [],
    'backup_folder_path' => storage_path('toth/backups'),
    'task_delay_seconds' => 30,
    'max_attempts' => 3,
    'grace_period_seconds' => 60,
];
PHP;

    public const CONFIG_WITH_MAX_ATTEMPTS = <<<'PHP'
<?php

return [
    'archivables' => [],
    'backup_folder_path' => storage_path('toth/backups'),
    'task_delay_seconds' => 5,
    'max_attempts' => 10,
    'grace_period_seconds' => 60,
];
PHP;

    public const CONFIG_WITH_GRACE_PERIOD = <<<'PHP'
<?php

return [
    'archivables' => [],
    'backup_folder_path' => storage_path('toth/backups'),
    'task_delay_seconds' => 5,
    'max_attempts' => 3,
    'grace_period_seconds' => 3600,
];
PHP;
}

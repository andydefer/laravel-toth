<?php

// tests/Integration/Tasks/BackupArchiveTaskTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Tasks;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelToth\Configs\TothConfig;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Records\ArchiveRecord;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\LaravelToth\Tasks\BackupArchiveTask;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelToth\Tests\IntegrationTestCase;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Directives\TasksProcessDirective;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

final class BackupArchiveTaskTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private UniqueTaskServiceInterface $uniqueTaskService;

    private ArchiveRepository $archiveRepository;

    private TothConfigInterface $config;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 0));

        $this->app['config']->set('toth.backup_folder_path', storage_path('toth/backups_test'));

        $this->app->singleton(
            TothConfigInterface::class,
            function ($app) {
                return new TothConfig($app['config']);
            }
        );

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $this->service->getKernel()->addDirective(TasksProcessDirective::class);

        $this->uniqueTaskService = $this->app->make(UniqueTaskServiceInterface::class);
        $this->archiveRepository = $this->app->make(ArchiveRepository::class);
        $this->config = $this->app->make(TothConfigInterface::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        $this->service->destroy();

        $backupPath = $this->config->getBackupFolderPath();
        if (File::exists($backupPath)) {
            File::deleteDirectory($backupPath);
        }

        parent::tearDown();
    }

    private function createArchive(): Archive
    {
        $user = TestUser::create([
            'name' => 'Backup Test User',
            'email' => 'backup@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $archive = $this->archiveRepository->create(ArchiveRecord::from([
            'table_name' => 'test_users',
            'row_id' => $user->id,
            'model_class' => TestUser::class,
            'data' => $user->toArray(),
            'last_save_at' => now()->toIso8601String(),
        ]));

        return $archive;
    }

    private function registerBackupTask(int $archiveId): void
    {
        $payload = StrictDataObject::from([
            'archive_id' => $archiveId,
        ]);

        $config = UniqueTaskConfigRecord::from([
            'scheduled_at' => now()->subSeconds(5)->toIso8601String(),
            'max_attempts' => 3,
            'grace_period' => 60,
            'description' => 'Backup archive task',
        ]);

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(BackupArchiveTask::class),
            $payload,
            $config
        );
    }

    public function test_task_creates_backup_file_successfully(): void
    {
        $archive = $this->createArchive();

        $this->registerBackupTask($archive->id);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $backupPath = $this->config->getBackupFolderPath();
        $filePath = $backupPath.'/test_users/'.$archive->row_id.'.php';

        $this->assertTrue(File::exists($filePath));

        $data = require $filePath;
        $this->assertEquals($archive->data->toArray(), $data);
    }

    public function test_task_creates_directory_if_not_exists(): void
    {
        $archive = $this->createArchive();

        $backupPath = $this->config->getBackupFolderPath();
        $directory = $backupPath.'/test_users';

        if (File::exists($directory)) {
            File::deleteDirectory($directory);
        }

        $this->assertFalse(File::exists($directory));

        $this->registerBackupTask($archive->id);
        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertTrue(File::exists($directory));

        $filePath = $directory.'/'.$archive->row_id.'.php';
        $this->assertTrue(File::exists($filePath));
    }

    public function test_task_handles_archive_not_found(): void
    {
        $this->registerBackupTask(99999);

        $response = $this->service->run('tasks:process --verbose');

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
    }

    public function test_task_uses_backup_file_helper(): void
    {
        $archive = $this->createArchive();

        $this->registerBackupTask($archive->id);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $backupPath = $this->config->getBackupFolderPath();
        $filePath = $backupPath.'/test_users/'.$archive->row_id.'.php';

        $this->assertTrue(File::exists($filePath));
        $this->assertEquals($archive->data->toArray(), require $filePath);
    }
}

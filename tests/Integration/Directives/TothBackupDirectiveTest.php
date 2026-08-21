<?php

// tests/Integration/Directives/TothBackupDirectiveTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelToth\Configs\TothConfig;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Directives\TothBackupDirective;
use AndyDefer\LaravelToth\Records\ArchiveFiltersRecord;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelToth\Tests\IntegrationTestCase;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Task\Directives\TasksProcessDirective;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

final class TothBackupDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private ArchiveRepository $archiveRepository;

    private TothConfigInterface $config;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 0));

        $this->app['config']->set('toth.archivables', [TestUser::class]);
        $this->app['config']->set('toth.backup_folder_path', storage_path('toth/backups_test'));

        $this->app->singleton(
            TothConfigInterface::class,
            function ($app) {
                return new TothConfig($app['config']);
            }
        );

        $this->config = $this->app->make(TothConfigInterface::class);
        $this->archiveRepository = $this->app->make(ArchiveRepository::class);

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $this->service->getKernel()->addDirective(TothBackupDirective::class);
        $this->service->getKernel()->addDirective(TasksProcessDirective::class);
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

    private function runTasks(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 15));
        $this->service->run('tasks:process');
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 0));
    }

    public function test_backup_all_tables_when_no_tables_specified(): void
    {
        $user1 = TestUser::create([
            'name' => 'User One',
            'email' => 'user1@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $user2 = TestUser::create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $response = $this->service->run('toth:backup');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $filters = ArchiveFiltersRecord::from([
            'model_class' => TestUser::class,
        ]);

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );

        $this->assertCount(2, $archives);
    }

    public function test_backup_specific_tables(): void
    {
        $user = TestUser::create([
            'name' => 'Specific User',
            'email' => 'specific@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $response = $this->service->run('toth:backup [test_users]');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
        ]);

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );

        $this->assertCount(1, $archives);
        $this->assertEquals($user->name, $archives->first()->data['name']);
    }

    public function test_backup_with_only_files_flag(): void
    {
        $user = TestUser::create([
            'name' => 'Files Only User',
            'email' => 'filesonly@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->service->run('toth:backup');
        $this->runTasks();

        $backupPath = $this->config->getBackupFolderPath();
        $filePath = $backupPath.'/test_users/'.$user->id.'.php';
        $this->assertTrue(File::exists($filePath), 'Backup file does not exist at: '.$filePath);

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
        ]);

        $archive = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        )->first();

        $this->assertNotNull($archive, 'Archive should exist before deletion');
        $this->archiveRepository->delete($archive->id);

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );
        $this->assertCount(0, $archives);

        $response = $this->service->run('toth:backup --only-files');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );

        $this->assertCount(1, $archives);
        $this->assertEquals($user->name, $archives->first()->data['name']);
    }

    public function test_backup_with_only_db_flag(): void
    {
        $user = TestUser::create([
            'name' => 'DB Only User',
            'email' => 'dbonly@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $response = $this->service->run('toth:backup --only-db');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
        ]);

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );

        $this->assertCount(1, $archives);
        $this->assertEquals($user->name, $archives->first()->data['name']);
    }

    public function test_backup_with_both_flags_returns_error(): void
    {
        $response = $this->service->run('toth:backup --only-files --only-db');

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('You cannot use --only-files and --only-db', $response->output);
    }

    public function test_backup_with_empty_tables_uses_config(): void
    {
        $user = TestUser::create([
            'name' => 'Config User',
            'email' => 'config@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $response = $this->service->run('toth:backup');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
        ]);

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );

        $this->assertCount(1, $archives);
    }

    public function test_backup_with_alias(): void
    {
        $user = TestUser::create([
            'name' => 'Alias User',
            'email' => 'alias@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $response = $this->service->run('backup');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
        ]);

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );

        $this->assertCount(1, $archives);
    }
}

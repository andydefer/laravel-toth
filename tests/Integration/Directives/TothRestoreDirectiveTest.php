<?php

// tests/Integration/Directives/TothRestoreDirectiveTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelToth\Configs\TothConfig;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Directives\TothBackupDirective;
use AndyDefer\LaravelToth\Directives\TothRestoreDirective;
use AndyDefer\LaravelToth\Records\ArchiveFiltersRecord;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelToth\Tests\IntegrationTestCase;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Task\Directives\TasksProcessDirective;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

final class TothRestoreDirectiveTest extends IntegrationTestCase
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
        $this->service->getKernel()->addDirective(TothRestoreDirective::class);
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

    private function createBackup(): void
    {
        $this->service->run('toth:backup');
        $this->runTasks();
    }

    public function test_restore_all_tables_when_no_tables_specified(): void
    {
        $user = TestUser::create([
            'name' => 'Restore All User',
            'email' => 'restoreall@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->createBackup();

        $userId = $user->id;
        $user->delete();

        $response = $this->service->run('toth:restore');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Restore All User', $restored->name);
        $this->assertEquals('restoreall@example.com', $restored->email);
    }

    public function test_restore_specific_tables(): void
    {
        $user = TestUser::create([
            'name' => 'Specific Restore User',
            'email' => 'specificrestore@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->createBackup();

        $userId = $user->id;
        $user->delete();

        $response = $this->service->run('toth:restore [test_users]');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Specific Restore User', $restored->name);
    }

    public function test_restore_with_only_files_flag(): void
    {
        $user = TestUser::create([
            'name' => 'Files Restore User',
            'email' => 'filesrestore@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->createBackup();

        $backupPath = $this->config->getBackupFolderPath();
        $filePath = $backupPath.'/test_users/'.$user->id.'.php';
        $this->assertTrue(File::exists($filePath));

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
        ]);

        $archive = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        )->first();

        if ($archive) {
            $this->archiveRepository->delete($archive->id);
        }

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );
        $this->assertCount(0, $archives);

        $userId = $user->id;
        $user->delete();

        $response = $this->service->run('toth:restore --only-files');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Files Restore User', $restored->name);
        $this->assertEquals('filesrestore@example.com', $restored->email);
    }

    public function test_restore_with_only_db_flag(): void
    {
        $user = TestUser::create([
            'name' => 'DB Restore User',
            'email' => 'dbrestore@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->createBackup();

        $userId = $user->id;
        $user->delete();

        $response = $this->service->run('toth:restore --only-db');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('DB Restore User', $restored->name);
    }

    public function test_restore_with_both_flags_returns_error(): void
    {
        $response = $this->service->run('toth:restore --only-files --only-db');

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('You cannot use --only-files and --only-db', $response->output);
    }

    public function test_restore_with_empty_tables_uses_config(): void
    {
        $user = TestUser::create([
            'name' => 'Config Restore User',
            'email' => 'configrestore@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->createBackup();

        $userId = $user->id;
        $user->delete();

        $response = $this->service->run('toth:restore');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
    }

    public function test_restore_with_alias(): void
    {
        $user = TestUser::create([
            'name' => 'Alias Restore User',
            'email' => 'aliasrestore@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->createBackup();

        $userId = $user->id;
        $user->delete();

        $response = $this->service->run('restore');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
    }

    public function test_restore_when_no_archive_exists(): void
    {
        $user = TestUser::create([
            'name' => 'No Archive User',
            'email' => 'noarchive@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $userId = $user->id;
        $user->delete();

        $response = $this->service->run('toth:restore');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNull($restored);
    }
}

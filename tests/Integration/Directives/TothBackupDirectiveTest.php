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

    private int $originalOutputBufferingLevel;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Démarrer l'obfuscation de la sortie
        $this->startOutputBuffering();

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

        // ✅ Capturer la sortie
        $this->captureOutput();

        parent::tearDown();
    }

    /**
     * Démarre l'obfuscation de la sortie pour les barres de progression.
     */
    private function startOutputBuffering(): void
    {
        $this->originalOutputBufferingLevel = ob_get_level();

        if (! ob_get_level()) {
            ob_start();
        }
    }

    /**
     * Capture et supprime la sortie des barres de progression.
     */
    private function captureOutput(): void
    {
        if (ob_get_level() > $this->originalOutputBufferingLevel) {
            ob_end_clean();
        } elseif (ob_get_level() > 0) {
            ob_clean();
        }
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

    // ============================================================
    // TESTS POUR LE FLAG --mute
    // ============================================================

    public function test_backup_with_mute_flag_disables_progress_bars(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Backup User',
            'email' => 'mutebackup@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        // ✅ Réactiver le buffering pour capturer la sortie
        $this->captureOutput();
        $this->startOutputBuffering();

        $response = $this->service->run('toth:backup --mute');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // ✅ Vérifier que la barre de progression n'est PAS affichée
        $this->assertStringNotContainsString('[████████', $response->output);
        $this->assertStringNotContainsString('Backing up models', $response->output);

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
        $this->assertEquals('Mute Backup User', $archives->first()->data['name']);
    }

    public function test_backup_with_mute_and_only_files_flag(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Files User',
            'email' => 'mutefiles@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->service->run('toth:backup');
        $this->runTasks();

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

        $this->assertNotNull($archive);
        $this->archiveRepository->delete($archive->id);

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );
        $this->assertCount(0, $archives);

        // ✅ Réactiver le buffering pour capturer la sortie
        $this->captureOutput();
        $this->startOutputBuffering();

        $response = $this->service->run('toth:backup --only-files --mute');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // ✅ Vérifier que la barre de progression n'est PAS affichée
        $this->assertStringNotContainsString('[████████', $response->output);
        $this->assertStringNotContainsString('Restoring from files', $response->output);

        $this->runTasks();

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );

        $this->assertCount(1, $archives);
        $this->assertEquals('Mute Files User', $archives->first()->data['name']);
    }

    public function test_backup_with_mute_and_only_db_flag(): void
    {
        $user = TestUser::create([
            'name' => 'Mute DB User',
            'email' => 'mutedb@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        // ✅ Réactiver le buffering pour capturer la sortie
        $this->captureOutput();
        $this->startOutputBuffering();

        $response = $this->service->run('toth:backup --only-db --mute');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // ✅ Vérifier que la barre de progression n'est PAS affichée
        $this->assertStringNotContainsString('[████████', $response->output);
        $this->assertStringNotContainsString('Backing up models', $response->output);

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
        $this->assertEquals('Mute DB User', $archives->first()->data['name']);
    }

    public function test_backup_with_mute_and_specific_tables(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Specific User',
            'email' => 'mutespecific@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        // ✅ Réactiver le buffering pour capturer la sortie
        $this->captureOutput();
        $this->startOutputBuffering();

        $response = $this->service->run('toth:backup [test_users] --mute');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // ✅ Vérifier que la barre de progression n'est PAS affichée
        $this->assertStringNotContainsString('[████████', $response->output);
        $this->assertStringNotContainsString('Backing up models', $response->output);

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
        $this->assertEquals('Mute Specific User', $archives->first()->data['name']);
    }

    public function test_backup_with_mute_and_alias(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Alias User',
            'email' => 'mutealias@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        // ✅ Réactiver le buffering pour capturer la sortie
        $this->captureOutput();
        $this->startOutputBuffering();

        $response = $this->service->run('backup --mute');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // ✅ Vérifier que la barre de progression n'est PAS affichée
        $this->assertStringNotContainsString('[████████', $response->output);
        $this->assertStringNotContainsString('Backing up models', $response->output);

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
        $this->assertEquals('Mute Alias User', $archives->first()->data['name']);
    }

    public function test_backup_with_mute_does_not_affect_functionality(): void
    {
        $user1 = TestUser::create([
            'name' => 'Mute User One',
            'email' => 'muteone@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $user2 = TestUser::create([
            'name' => 'Mute User Two',
            'email' => 'mutetwo@example.com',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $response = $this->service->run('toth:backup --mute');

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
}

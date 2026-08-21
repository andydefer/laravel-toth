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

    // ============================================================
    // TESTS POUR LE FLAG --mute
    // ============================================================

    public function test_restore_with_mute_flag_disables_progress_bars(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Restore User',
            'email' => 'muterestore@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->createBackup();

        $userId = $user->id;
        $user->delete();

        // ✅ Réactiver le buffering pour capturer la sortie
        $this->captureOutput();
        $this->startOutputBuffering();

        $response = $this->service->run('toth:restore --mute');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // ✅ Vérifier que la barre de progression n'est PAS affichée
        $this->assertStringNotContainsString('[████████', $response->output);
        $this->assertStringNotContainsString('Restoring from database', $response->output);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Mute Restore User', $restored->name);
        $this->assertEquals('muterestore@example.com', $restored->email);
    }

    public function test_restore_with_mute_and_only_files_flag(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Files Restore User',
            'email' => 'mutefilesrestore@example.com',
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

        // ✅ Réactiver le buffering pour capturer la sortie
        $this->captureOutput();
        $this->startOutputBuffering();

        $response = $this->service->run('toth:restore --only-files --mute');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // ✅ Vérifier que la barre de progression n'est PAS affichée
        $this->assertStringNotContainsString('[████████', $response->output);
        $this->assertStringNotContainsString('Restoring from files', $response->output);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Mute Files Restore User', $restored->name);
        $this->assertEquals('mutefilesrestore@example.com', $restored->email);
    }

    public function test_restore_with_mute_and_only_db_flag(): void
    {
        $user = TestUser::create([
            'name' => 'Mute DB Restore User',
            'email' => 'mutedbrestore@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->createBackup();

        $userId = $user->id;
        $user->delete();

        // ✅ Réactiver le buffering pour capturer la sortie
        $this->captureOutput();
        $this->startOutputBuffering();

        $response = $this->service->run('toth:restore --only-db --mute');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // ✅ Vérifier que la barre de progression n'est PAS affichée
        $this->assertStringNotContainsString('[████████', $response->output);
        $this->assertStringNotContainsString('Restoring from database', $response->output);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Mute DB Restore User', $restored->name);
        $this->assertEquals('mutedbrestore@example.com', $restored->email);
    }

    public function test_restore_with_mute_and_specific_tables(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Specific Restore User',
            'email' => 'mutespecificrestore@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->createBackup();

        $userId = $user->id;
        $user->delete();

        // ✅ Réactiver le buffering pour capturer la sortie
        $this->captureOutput();
        $this->startOutputBuffering();

        $response = $this->service->run('toth:restore [test_users] --mute');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // ✅ Vérifier que la barre de progression n'est PAS affichée
        $this->assertStringNotContainsString('[████████', $response->output);
        $this->assertStringNotContainsString('Restoring from database', $response->output);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Mute Specific Restore User', $restored->name);
        $this->assertEquals('mutespecificrestore@example.com', $restored->email);
    }

    public function test_restore_with_mute_and_alias(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Alias Restore User',
            'email' => 'mutealiasrestore@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->createBackup();

        $userId = $user->id;
        $user->delete();

        // ✅ Réactiver le buffering pour capturer la sortie
        $this->captureOutput();
        $this->startOutputBuffering();

        $response = $this->service->run('restore --mute');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // ✅ Vérifier que la barre de progression n'est PAS affichée
        $this->assertStringNotContainsString('[████████', $response->output);
        $this->assertStringNotContainsString('Restoring from database', $response->output);

        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Mute Alias Restore User', $restored->name);
        $this->assertEquals('mutealiasrestore@example.com', $restored->email);
    }

    public function test_restore_with_mute_does_not_affect_functionality(): void
    {
        $user1 = TestUser::create([
            'name' => 'Mute Restore One',
            'email' => 'muterestoreone@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $user2 = TestUser::create([
            'name' => 'Mute Restore Two',
            'email' => 'muterestoretwo@example.com',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $this->createBackup();

        $userId1 = $user1->id;
        $user1->delete();

        $userId2 = $user2->id;
        $user2->delete();

        $response = $this->service->run('toth:restore --mute');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->runTasks();

        $restored1 = TestUser::find($userId1);
        $this->assertNotNull($restored1);
        $this->assertEquals('Mute Restore One', $restored1->name);

        $restored2 = TestUser::find($userId2);
        $this->assertNotNull($restored2);
        $this->assertEquals('Mute Restore Two', $restored2->name);
    }
}

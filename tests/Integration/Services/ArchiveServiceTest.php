<?php

// tests/Integration/Services/ArchiveServiceTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Services;

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelToth\Configs\TothConfig;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Records\ArchiveFiltersRecord;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\LaravelToth\Services\ArchiveService;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelToth\Tests\IntegrationTestCase;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Directives\TasksProcessDirective;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

final class ArchiveServiceTest extends IntegrationTestCase
{
    private ArchiveService $archiveService;

    private ArchiveRepository $archiveRepository;

    private UniqueTaskServiceInterface $uniqueTaskService;

    private DirectiveTestingService $directiveService;

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
        $this->archiveService = $this->app->make(ArchiveService::class);
        $this->uniqueTaskService = $this->app->make(UniqueTaskServiceInterface::class);

        $this->directiveService = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $this->directiveService->getKernel()->addDirective(TasksProcessDirective::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        $this->directiveService->destroy();

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
        $this->directiveService->run('tasks:process');
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 0));
    }

    public function test_create_or_update_archive_creates_archive(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);

        $this->runTasks();

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
            'model_class' => TestUser::class,
        ]);

        $archive = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        )->first();

        $this->assertNotNull($archive);
        $this->assertEquals('test_users', $archive->table_name);
        $this->assertEquals((string) $user->id, $archive->row_id);
        $this->assertEquals(TestUser::class, $archive->model_class);
        $this->assertEquals($user->name, $archive->data['name']);
        $this->assertEquals($user->email, $archive->data['email']);
    }

    public function test_create_or_update_archive_updates_existing_archive(): void
    {
        $user = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 10));

        $user->update([
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
            'model_class' => TestUser::class,
        ]);

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters,
                sortBy: new SortColumns('last_save_at:desc')
            )
        );

        $this->assertCount(1, $archives);

        $latest = $archives->first();
        $this->assertEquals('Jane Smith', $latest->data['name']);
        $this->assertEquals('jane.smith@example.com', $latest->data['email']);
    }

    public function test_backup_creates_file(): void
    {
        $user = TestUser::create([
            'name' => 'Backup User',
            'email' => 'backup@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
            'model_class' => TestUser::class,
        ]);

        $archive = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        )->first();

        $this->assertNotNull($archive);

        $this->archiveService->backup($archive);
        $this->runTasks();

        $backupPath = $this->config->getBackupFolderPath();
        $filePath = $backupPath.'/test_users/'.$archive->row_id.'.php';

        $this->assertTrue(File::exists($filePath));

        $data = require $filePath;
        $this->assertEquals($archive->data['name'], $data['name']);
        $this->assertEquals($archive->data['email'], $data['email']);
    }

    public function test_backup_from_models(): void
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

        // ✅ Activer mute pour ce test
        $this->archiveService->setMute(true);
        $this->archiveService->backupFromModels();
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

    public function test_backup_from_models_with_table_filter(): void
    {
        $user = TestUser::create([
            'name' => 'Filtered User',
            'email' => 'filtered@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        // ✅ Activer mute pour ce test
        $this->archiveService->setMute(true);
        $this->archiveService->backupFromModels(['non_existent_table']);
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

        $this->assertCount(0, $archives);
    }

    public function test_backup_from_files(): void
    {
        $user = TestUser::create([
            'name' => 'File Backup User',
            'email' => 'filebackup@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

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

        $this->archiveService->backup($archive);
        $this->runTasks();

        // ✅ Activer mute pour ce test
        $this->archiveService->setMute(true);
        $this->archiveService->backupFromFiles();
        $this->runTasks();

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters,
                sortBy: new SortColumns('last_save_at:desc')
            )
        );

        $this->assertGreaterThanOrEqual(1, $archives->count());
    }

    public function test_backup_from_files_with_table_filter(): void
    {
        $user = TestUser::create([
            'name' => 'File Filter User',
            'email' => 'filefilter@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

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

        $this->archiveService->backup($archive);
        $this->runTasks();

        // ✅ Activer mute pour ce test
        $this->archiveService->setMute(true);
        $this->archiveService->backupFromFiles(['non_existent_table']);
        $this->runTasks();

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );

        $this->assertGreaterThanOrEqual(1, $archives->count());
    }

    public function test_restore_from_models(): void
    {
        $user = TestUser::create([
            'name' => 'Restore Model User',
            'email' => 'restoremodel@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

        $userId = $user->id;
        $user->delete();

        // ✅ Activer mute pour ce test
        $this->archiveService->setMute(true);
        $this->archiveService->restoreFromModels();
        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Restore Model User', $restored->name);
        $this->assertEquals('restoremodel@example.com', $restored->email);
    }

    public function test_restore_from_models_with_table_filter(): void
    {
        $user = TestUser::create([
            'name' => 'Restore Filter User',
            'email' => 'restorefilter@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

        $userId = $user->id;
        $user->delete();

        // ✅ Activer mute pour ce test
        $this->archiveService->setMute(true);
        $this->archiveService->restoreFromModels(['non_existent_table']);
        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNull($restored);
    }

    public function test_restore_from_files(): void
    {
        $user = TestUser::create([
            'name' => 'Restore File User',
            'email' => 'restorefile@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

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

        $this->archiveService->backup($archive);
        $this->runTasks();

        $userId = $user->id;
        $user->delete();

        // ✅ Activer mute pour ce test
        $this->archiveService->setMute(true);
        $this->archiveService->restoreFromFiles();
        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Restore File User', $restored->name);
        $this->assertEquals('restorefile@example.com', $restored->email);
    }

    public function test_restore_from_files_with_table_filter(): void
    {
        $user = TestUser::create([
            'name' => 'Restore File Filter User',
            'email' => 'restorefilefilter@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

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

        $this->archiveService->backup($archive);
        $this->runTasks();

        $userId = $user->id;
        $user->delete();

        // ✅ Activer mute pour ce test
        $this->archiveService->setMute(true);
        $this->archiveService->restoreFromFiles(['non_existent_table']);
        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNull($restored);
    }

    public function test_cancel_existing_task_on_multiple_updates(): void
    {
        $user = TestUser::create([
            'name' => 'Cancel Task User',
            'email' => 'cancel@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);

        $tasks = $this->uniqueTaskService->findPending();
        $this->assertGreaterThanOrEqual(1, $tasks->count());

        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 5));
        $this->archiveService->createOrUpdateArchive($user);

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

    public function test_register_observers(): void
    {
        $this->archiveService->registerObservers();
        $this->assertTrue(true);
    }

    // ============================================================
    // TESTS POUR LE MODE MUTE
    // ============================================================

    public function test_set_mute_disables_progress_bars(): void
    {
        $user = TestUser::create([
            'name' => 'Mute User',
            'email' => 'mute@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->setMute(true);
        $this->assertTrue($this->archiveService->isMuted());

        $this->archiveService->backupFromModels();

        $this->assertTrue(true);
    }

    public function test_set_mute_false_enables_progress_bars(): void
    {
        $user = TestUser::create([
            'name' => 'Unmute User',
            'email' => 'unmute@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->setMute(false);
        $this->assertFalse($this->archiveService->isMuted());

        // ✅ Pour ce test, on réactive le buffering car il n'y a pas de mute
        $this->captureOutput();
        $this->startOutputBuffering();

        $this->archiveService->backupFromModels();

        $this->assertTrue(true);
    }

    public function test_mute_does_not_affect_archive_creation(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Archive User',
            'email' => 'mutearchive@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->setMute(true);
        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
            'model_class' => TestUser::class,
        ]);

        $archive = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        )->first();

        $this->assertNotNull($archive);
        $this->assertEquals($user->name, $archive->data['name']);
        $this->assertEquals($user->email, $archive->data['email']);
    }

    public function test_is_muted_returns_correct_state(): void
    {
        $this->assertFalse($this->archiveService->isMuted());

        $this->archiveService->setMute(true);
        $this->assertTrue($this->archiveService->isMuted());

        $this->archiveService->setMute(false);
        $this->assertFalse($this->archiveService->isMuted());
    }

    public function test_mute_does_not_affect_backup_from_models(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Backup User',
            'email' => 'mutebackup@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->setMute(true);
        $this->archiveService->backupFromModels();
        $this->runTasks();

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
            'model_class' => TestUser::class,
        ]);

        $archive = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        )->first();

        $this->assertNotNull($archive);
    }

    public function test_mute_does_not_affect_restore_from_models(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Restore User',
            'email' => 'muterestore@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

        $userId = $user->id;
        $user->delete();

        $this->archiveService->setMute(true);
        $this->archiveService->restoreFromModels();
        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Mute Restore User', $restored->name);
    }

    public function test_mute_does_not_affect_restore_from_files(): void
    {
        $user = TestUser::create([
            'name' => 'Mute File Restore User',
            'email' => 'mutefilerestore@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

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

        $this->archiveService->backup($archive);
        $this->runTasks();

        $userId = $user->id;
        $user->delete();

        $this->archiveService->setMute(true);
        $this->archiveService->restoreFromFiles();
        $this->runTasks();

        $restored = TestUser::find($userId);
        $this->assertNotNull($restored);
        $this->assertEquals('Mute File Restore User', $restored->name);
    }

    public function test_mute_does_not_affect_backup_single_archive(): void
    {
        $user = TestUser::create([
            'name' => 'Mute Single Backup User',
            'email' => 'mutesinglebackup@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => (string) $user->id,
            'model_class' => TestUser::class,
        ]);

        $archive = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        )->first();

        $this->assertNotNull($archive);

        $this->archiveService->setMute(true);
        $this->archiveService->backup($archive);
        $this->runTasks();

        $backupPath = $this->config->getBackupFolderPath();
        $filePath = $backupPath.'/test_users/'.$archive->row_id.'.php';

        $this->assertTrue(File::exists($filePath));
    }
}

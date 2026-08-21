<?php

// tests/Integration/Services/ArchiveServiceTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Services;

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelToth\Configs\TothConfig;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Models\Archive;
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

        parent::tearDown();
    }

    private function runTasks(): void
    {
        // Avancer le temps de 15 secondes pour être sûr d'exécuter toutes les tâches
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 15));
        $this->directiveService->run('tasks:process');
        // Remettre le temps initial
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 0));
    }

    private function runTasksWithDelay(): void
    {
        // Avancer le temps de 10 secondes pour exécuter les tâches planifiées
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 10));
        $this->directiveService->run('tasks:process');
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

        // Exécuter les tâches
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

        // Première archive
        $this->archiveService->createOrUpdateArchive($user);
        $this->runTasks();

        // Avancer le temps
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 10));

        $user->update([
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
        ]);

        // Deuxième archive (devrait mettre à jour la première)
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

        // ✅ UNE seule archive pour un modèle
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

        // Première mise à jour
        $this->archiveService->createOrUpdateArchive($user);

        // Vérifier qu'une tâche est en attente
        $tasks = $this->uniqueTaskService->findPending();
        $this->assertGreaterThanOrEqual(1, $tasks->count());

        // Deuxième mise à jour immédiate
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 5));
        $this->archiveService->createOrUpdateArchive($user);

        // Exécuter les tâches pour voir le résultat
        $this->runTasks();

        // Vérifier qu'il n'y a qu'une seule archive (la plus récente)
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
}

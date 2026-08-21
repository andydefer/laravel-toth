<?php

// tests/Integration/Tasks/UpdateOrCreateArchiveTaskTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Tasks;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelToth\Configs\TothConfig;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Records\ArchiveFiltersRecord;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\LaravelToth\Tasks\UpdateOrCreateArchiveTask;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelToth\Tests\IntegrationTestCase;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Directives\TasksProcessDirective;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

final class UpdateOrCreateArchiveTaskTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private UniqueTaskServiceInterface $uniqueTaskService;

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
        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $this->service->getKernel()->addDirective(TasksProcessDirective::class);

        $this->uniqueTaskService = $this->app->make(UniqueTaskServiceInterface::class);
        $this->archiveRepository = $this->app->make(ArchiveRepository::class);
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

    private function registerUpdateOrCreateTask(
        string $modelClass,
        int $modelId
    ): void {
        $payload = StrictDataObject::from([
            'model_class' => $modelClass,
            'model_id' => $modelId,
        ]);

        $config = UniqueTaskConfigRecord::from([
            'scheduled_at' => now()->subSeconds(5)->toIso8601String(),
            'max_attempts' => 3,
            'grace_period' => 60,
            'description' => 'Update or create archive task',
        ]);

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(UpdateOrCreateArchiveTask::class),
            $payload,
            $config
        );
    }

    public function test_task_creates_archive_successfully(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->registerUpdateOrCreateTask(
            modelClass: TestUser::class,
            modelId: $user->id
        );

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

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
        $this->assertEquals($user->id, $archive->row_id);
        $this->assertEquals(TestUser::class, $archive->model_class);
        $this->assertEquals($user->name, $archive->data['name']);
        $this->assertEquals($user->email, $archive->data['email']);
    }

    public function test_task_creates_backup_file(): void
    {
        $user = TestUser::create([
            'name' => 'Backup File User',
            'email' => 'backupfile@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->registerUpdateOrCreateTask(TestUser::class, $user->id);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $backupPath = $this->config->getBackupFolderPath();
        $filePath = $backupPath.'/test_users/'.$user->id.'.php';

        $this->assertTrue(File::exists($filePath));

        $data = require $filePath;
        $this->assertEquals($user->name, $data['name']);
        $this->assertEquals($user->email, $data['email']);
    }

    public function test_task_updates_existing_archive(): void
    {
        $user = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $this->registerUpdateOrCreateTask(TestUser::class, $user->id);
        $this->service->run('tasks:process');

        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 10));

        $user->update([
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
        ]);

        $this->registerUpdateOrCreateTask(TestUser::class, $user->id);
        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

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

    public function test_task_updates_backup_file_when_archive_updated(): void
    {
        $user = TestUser::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->registerUpdateOrCreateTask(TestUser::class, $user->id);
        $this->service->run('tasks:process');

        Carbon::setTestNow(Carbon::create(2026, 7, 26, 12, 0, 10));

        $user->update([
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $this->registerUpdateOrCreateTask(TestUser::class, $user->id);
        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $backupPath = $this->config->getBackupFolderPath();
        $filePath = $backupPath.'/test_users/'.$user->id.'.php';

        $this->assertTrue(File::exists($filePath));

        $data = require $filePath;
        $this->assertEquals('Updated Name', $data['name']);
        $this->assertEquals('updated@example.com', $data['email']);
    }

    public function test_task_handles_user_not_found(): void
    {
        $this->registerUpdateOrCreateTask(
            modelClass: TestUser::class,
            modelId: 99999
        );

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => '99999',
            'model_class' => TestUser::class,
        ]);

        $archive = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        )->first();

        $this->assertNull($archive);
    }
}

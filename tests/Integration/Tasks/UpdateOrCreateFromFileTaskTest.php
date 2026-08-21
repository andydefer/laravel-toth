<?php

// tests/Integration/Tasks/UpdateOrCreateFromFileTaskTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Tasks;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelToth\Configs\TothConfig;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Records\ArchiveFiltersRecord;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\LaravelToth\Tasks\UpdateOrCreateFromFileTask;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelToth\Tests\IntegrationTestCase;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Directives\TasksProcessDirective;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

final class UpdateOrCreateFromFileTaskTest extends IntegrationTestCase
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
        $this->archiveRepository = $this->app->make(ArchiveRepository::class);

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $this->service->getKernel()->addDirective(TasksProcessDirective::class);

        $this->uniqueTaskService = $this->app->make(UniqueTaskServiceInterface::class);
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

    private function createBackupFile(int $userId, array $data): void
    {
        $backupPath = $this->config->getBackupFolderPath();
        $directory = $backupPath.'/test_users';

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePath = $directory.'/'.$userId.'.php';
        $content = '<?php'.PHP_EOL.PHP_EOL;
        $content .= 'return '.var_export($data, true).';'.PHP_EOL;
        File::put($filePath, $content);
    }

    private function registerTask(string $tableName, string $rowId): void
    {
        $payload = StrictDataObject::from([
            'table_name' => $tableName,
            'row_id' => $rowId,
        ]);

        $config = UniqueTaskConfigRecord::from([
            'scheduled_at' => now()->subSeconds(5)->toIso8601String(),
            'max_attempts' => 3,
            'grace_period' => 60,
            'description' => 'Update or create from file task',
        ]);

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(UpdateOrCreateFromFileTask::class),
            $payload,
            $config
        );
    }

    public function test_task_creates_archive_from_file(): void
    {
        $user = TestUser::create([
            'name' => 'File User',
            'email' => 'file@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $data = $user->toArray();
        unset($data['created_at'], $data['updated_at']);

        $this->createBackupFile($user->id, $data);

        $this->registerTask('test_users', (string) $user->id);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

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

        $archive = $archives->first();
        $this->assertEquals($user->name, $archive->data['name']);
        $this->assertEquals($user->email, $archive->data['email']);
        $this->assertEquals($user->status->value, $archive->data['status']);
        $this->assertEquals($user->role->value, $archive->data['role']);
    }

    public function test_task_updates_existing_archive_from_file(): void
    {
        $user = TestUser::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $data = $user->toArray();
        unset($data['created_at'], $data['updated_at']);
        $data['name'] = 'Updated From File';
        $data['email'] = 'updated@example.com';

        $this->createBackupFile($user->id, $data);

        $this->registerTask('test_users', (string) $user->id);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

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

        $archive = $archives->first();
        $this->assertEquals('Updated From File', $archive->data['name']);
        $this->assertEquals('updated@example.com', $archive->data['email']);
    }

    public function test_task_fails_when_file_not_found(): void
    {
        $this->registerTask('test_users', '99999');

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);

        $filters = ArchiveFiltersRecord::from([
            'table_name' => 'test_users',
            'row_id' => '99999',
        ]);

        $archives = $this->archiveRepository->findBy(
            new FindByRecord(
                filters: $filters
            )
        );

        $this->assertCount(0, $archives);
    }

    public function test_task_fails_when_file_is_empty(): void
    {
        $backupPath = $this->config->getBackupFolderPath();
        $directory = $backupPath.'/test_users';

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePath = $directory.'/1.php';
        File::put($filePath, '<?php return [];');

        $this->registerTask('test_users', '1');

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
    }

    public function test_task_fails_when_model_class_not_found(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $this->createBackupFile(1, $data);

        // Configurer avec un model qui n'existe pas
        $this->app['config']->set('toth.archivables', ['NonExistentModel']);

        $this->registerTask('test_users', '1');

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
    }
}

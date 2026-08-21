<?php

// tests/Integration/Tasks/RestoreArchiveTaskTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Tasks;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelToth\Configs\TothConfig;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Records\ArchiveRecord;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\LaravelToth\Tasks\RestoreArchiveTask;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelToth\Tests\IntegrationTestCase;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Directives\TasksProcessDirective;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

final class RestoreArchiveTaskTest extends IntegrationTestCase
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

    private function createArchiveForRestore(): Archive
    {
        $user = TestUser::create([
            'name' => 'Restore Test User',
            'email' => 'restore@example.com',
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

    private function registerRestoreTask(string $tableName, string $rowId): void
    {
        $payload = StrictDataObject::from([
            'table_name' => $tableName,
            'row_id' => $rowId,
        ]);

        $config = UniqueTaskConfigRecord::from([
            'scheduled_at' => now()->subSeconds(5)->toIso8601String(),
            'max_attempts' => 3,
            'grace_period' => 60,
            'description' => 'Restore archive task',
        ]);

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(RestoreArchiveTask::class),
            $payload,
            $config
        );
    }

    private function createBackupFile(int|string $rowId, array $data, int $timestampOffset = 0): void
    {
        $backupPath = $this->config->getBackupFolderPath();
        $directory = $backupPath.'/test_users';

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePath = $directory.'/'.$rowId.'.php';
        $content = '<?php'.PHP_EOL.PHP_EOL;
        $content .= 'return '.var_export($data, true).';'.PHP_EOL;
        File::put($filePath, $content);

        if ($timestampOffset !== 0) {
            $timestamp = Carbon::now()->addMinutes($timestampOffset)->timestamp;
            touch($filePath, $timestamp);
        }
    }

    /**
     * Converts a ClusterVO to a plain array for modification.
     */
    private function clusterToArray(ClusterVO $cluster): array
    {
        return $cluster->toArray();
    }

    public function test_task_restores_from_database_successfully(): void
    {
        $archive = $this->createArchiveForRestore();

        TestUser::where('id', $archive->row_id)->delete();

        $this->registerRestoreTask('test_users', $archive->row_id);

        $response = $this->service->run('tasks:process --verbose');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $restored = TestUser::find($archive->row_id);
        $this->assertNotNull($restored);
        $this->assertEquals($archive->data['name'], $restored->name);
        $this->assertEquals($archive->data['email'], $restored->email);
    }

    public function test_task_restores_from_backup_file_when_newer(): void
    {
        $archive = $this->createArchiveForRestore();

        // Convertir ClusterVO en array et le modifier
        $backupData = $archive->data->toArray();
        $backupData['name'] = 'Updated From Backup';
        $backupData['email'] = 'updated@example.com';

        $this->createBackupFile($archive->row_id, $backupData, 10);

        TestUser::where('id', $archive->row_id)->delete();

        $this->registerRestoreTask('test_users', $archive->row_id);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $restored = TestUser::find($archive->row_id);
        $this->assertNotNull($restored);
        $this->assertEquals('Updated From Backup', $restored->name);
        $this->assertEquals('updated@example.com', $restored->email);
    }

    public function test_task_uses_model_class_from_config_when_archive_missing(): void
    {
        $archive = $this->createArchiveForRestore();

        $this->createBackupFile($archive->row_id, $archive->data->toArray());

        $this->archiveRepository->delete($archive->id);

        TestUser::where('id', $archive->row_id)->delete();

        $this->registerRestoreTask('test_users', $archive->row_id);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $restored = TestUser::find($archive->row_id);
        $this->assertNotNull($restored);
        $this->assertEquals($archive->data['name'], $restored->name);
    }

    public function test_task_fails_when_model_class_not_found_in_config(): void
    {
        $user = TestUser::create([
            'name' => 'No Config User',
            'email' => 'noconfig@example.com',
            'status' => 'active',
            'role' => 'user',
        ]);

        $archive = $this->archiveRepository->create(ArchiveRecord::from([
            'table_name' => 'test_users',
            'row_id' => $user->id,
            'model_class' => 'NonExistentModel',
            'data' => $user->toArray(),
            'last_save_at' => now()->toIso8601String(),
        ]));

        $this->createBackupFile($archive->row_id, $archive->data->toArray());

        $this->archiveRepository->delete($archive->id);

        $this->app['config']->set('toth.archivables', ['NonExistentModel']);

        TestUser::where('id', $archive->row_id)->delete();

        $this->registerRestoreTask('test_users', $archive->row_id);

        $response = $this->service->run('tasks:process --verbose');

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);

        $error = $this->stripAnsi($response->output);
        $this->assertStringContainsString('No model_class found', $error);
    }

    public function test_task_fails_when_model_already_exists(): void
    {
        $archive = $this->createArchiveForRestore();

        $this->registerRestoreTask('test_users', $archive->row_id);

        $response = $this->service->run('tasks:process --verbose');

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);

        $error = $this->stripAnsi($response->output);
        $this->assertStringContainsString('already exists in database', $error);
    }

    public function test_task_fails_when_archive_not_found(): void
    {
        $this->registerRestoreTask('test_users', '99999');

        $response = $this->service->run('tasks:process --verbose');

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);

        $error = $this->stripAnsi($response->output);
        $this->assertStringContainsString('No data found to restore', $error);
    }

    public function test_task_gives_priority_to_database_over_backup_file(): void
    {
        $archive = $this->createArchiveForRestore();

        // Backup file : 10 minutes dans le passé
        $backupData = $archive->data->toArray();
        $backupData['name'] = 'Backup Name';
        $this->createBackupFile($archive->row_id, $backupData, -10);

        // Mettre à jour l'archive en DB
        $newData = $archive->data->toArray();
        $newData['name'] = 'DB Name';

        $archive->update([
            'data' => $newData,
            'last_save_at' => Carbon::create(2026, 7, 26, 12, 0, 15),
        ]);

        $archive->refresh();

        $this->assertEquals('DB Name', $archive->data['name']);

        TestUser::where('id', $archive->row_id)->delete();

        $this->registerRestoreTask('test_users', $archive->row_id);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $restored = TestUser::find($archive->row_id);
        $this->assertNotNull($restored);
        $this->assertEquals('DB Name', $restored->name);
    }

    public function test_task_gives_priority_to_backup_file_when_newer(): void
    {
        $archive = $this->createArchiveForRestore();

        $backupData = $archive->data->toArray();
        $backupData['name'] = 'Backup Name';
        $this->createBackupFile($archive->row_id, $backupData, 10);

        TestUser::where('id', $archive->row_id)->delete();

        $this->registerRestoreTask('test_users', $archive->row_id);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $restored = TestUser::find($archive->row_id);
        $this->assertNotNull($restored);
        $this->assertEquals('Backup Name', $restored->name);
    }
}

<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\LaravelToth\Configs\TothConfig;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Contracts\Services\ArchiveServiceInterface;
use AndyDefer\LaravelToth\Contracts\Services\DiscoveryServiceInterface;
use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Observers\ArchiveObserver;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\LaravelToth\Services\ArchiveService;
use AndyDefer\LaravelToth\Services\DiscoveryService;
use AndyDefer\LaravelToth\Utils\ProgressManager;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use Illuminate\Support\ServiceProvider;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Service provider for the Toth backup package.
 *
 * Registers package services, configuration, and observers.
 * Handles publishing of configuration and migration files.
 */
final class LaravelTothServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__.'/../config/toth.php';

    private const MIGRATION_PATH = __DIR__.'/../database/migrations';

    public function register(): void
    {
        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'toth'
        );

        $this->registerServices();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            $this->publishMigrations();
        }

        $this->registerObservers();
        $this->registerArchiveObserver();
    }

    /**
     * Registers all service bindings in the container.
     */
    private function registerServices(): void
    {
        // ============================================================
        // 1. Console
        // ============================================================
        $this->app->singleton(
            Console::class,
            function () {
                return new Console;
            }
        );

        // ============================================================
        // 2. ProgressManager
        // ============================================================
        $this->app->singleton(
            ProgressManager::class,
            function ($app) {
                return new ProgressManager(
                    $app->make(Console::class)
                );
            }
        );

        // ============================================================
        // 3. FileSystemService
        // ============================================================
        $this->app->singleton(
            FileSystemService::class,
            function () {
                return new FileSystemService;
            }
        );

        $this->app->bind(
            FileSystemInterface::class,
            FileSystemService::class
        );

        // ============================================================
        // 4. PhpParser
        // ============================================================
        $this->app->singleton(
            Parser::class,
            function () {
                return (new ParserFactory)->createForNewestSupportedVersion();
            }
        );

        // ============================================================
        // 5. DiscoveryService
        // ============================================================
        $this->app->singleton(
            DiscoveryService::class,
            function ($app) {
                return new DiscoveryService(
                    $app->make(FileSystemInterface::class),
                    $app->make(Parser::class)
                );
            }
        );

        $this->app->bind(
            DiscoveryServiceInterface::class,
            DiscoveryService::class
        );

        // ============================================================
        // 6. TothConfig
        // ============================================================
        $this->app->singleton(
            TothConfig::class,
            function ($app) {
                return new TothConfig($app['config']);
            }
        );

        $this->app->bind(
            TothConfigInterface::class,
            TothConfig::class
        );

        // ============================================================
        // 7. ArchiveRepository
        // ============================================================
        $this->app->singleton(
            ArchiveRepository::class,
            function () {
                return new ArchiveRepository;
            }
        );

        // ============================================================
        // 8. ArchiveService
        // ============================================================
        $this->app->singleton(
            ArchiveService::class,
            function ($app) {
                return new ArchiveService(
                    config: $app->make(TothConfigInterface::class),
                    taskService: $app->make(UniqueTaskServiceInterface::class),
                    archiveRepository: $app->make(ArchiveRepository::class),
                    progress: $app->make(ProgressManager::class),
                );
            }
        );

        $this->app->bind(
            ArchiveServiceInterface::class,
            ArchiveService::class
        );
    }

    /**
     * Publishes the configuration file.
     */
    private function publishConfig(): void
    {
        $this->publishes([
            self::CONFIG_PATH => config_path('toth.php'),
        ], 'toth-config');
    }

    /**
     * Publishes the migration files.
     */
    private function publishMigrations(): void
    {
        if (! is_dir(self::MIGRATION_PATH)) {
            return;
        }

        $this->publishes([
            self::MIGRATION_PATH => database_path('migrations'),
        ], 'toth-migrations');
    }

    /**
     * Registers observers for all archivable models.
     */
    private function registerObservers(): void
    {
        $archiveService = $this->app->make(ArchiveServiceInterface::class);
        $archiveService->registerObservers();
    }

    /**
     * Registers the observer for the Archive model.
     */
    private function registerArchiveObserver(): void
    {
        Archive::observe(ArchiveObserver::class);
    }
}

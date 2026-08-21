<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth;

use AndyDefer\LaravelToth\Configs\TothConfig;
use AndyDefer\LaravelToth\Contracts\Configs\TothConfigInterface;
use AndyDefer\LaravelToth\Contracts\Services\ArchiveServiceInterface;
use AndyDefer\LaravelToth\Models\Archive;
use AndyDefer\LaravelToth\Observers\ArchiveObserver;
use AndyDefer\LaravelToth\Repositories\ArchiveRepository;
use AndyDefer\LaravelToth\Services\ArchiveService;
use Illuminate\Support\ServiceProvider;

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
        $this->app->singleton(
            TothConfigInterface::class,
            TothConfig::class,
        );

        $this->app->singleton(
            ArchiveRepository::class,
            ArchiveRepository::class,
        );

        $this->app->singleton(
            ArchiveServiceInterface::class,
            ArchiveService::class,
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

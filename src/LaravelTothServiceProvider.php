<?php

// src/LaravelTothServiceProvider.php

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

final class LaravelTothServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/toth.php',
            'toth'
        );

        $this->app->singleton(
            abstract: TothConfigInterface::class,
            concrete: TothConfig::class,
        );

        $this->app->singleton(
            abstract: ArchiveRepository::class,
            concrete: ArchiveRepository::class,
        );

        $this->app->singleton(
            abstract: ArchiveServiceInterface::class,
            concrete: ArchiveService::class,
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/toth.php' => config_path('toth.php'),
            ], 'toth-config');
        }

        $this->registerObservers();
        $this->registerArchiveObserver();
    }

    private function registerObservers(): void
    {
        $archiveService = $this->app->make(ArchiveServiceInterface::class);
        $archiveService->registerObservers();
    }

    private function registerArchiveObserver(): void
    {
        Archive::observe(ArchiveObserver::class);
    }
}

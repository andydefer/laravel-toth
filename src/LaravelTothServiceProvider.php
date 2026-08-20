<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth;

use Illuminate\Support\ServiceProvider;

final class LaravelTothServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/toth.php',
            'toth'
        );

    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/toth.php' => config_path('toth.php'),
            ], 'toth-config');
        }
    }
}

<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests;

use AndyDefer\LaravelToth\LaravelTothServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected string $databasePath;

    protected function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]+m/', '', $text);
    }

    protected function normalize(mixed $collection): array
    {
        return action_normalizer_chain(true)->normalize($collection);
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelTothServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void {}

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    protected function runMigrations(): void
    {
        $fixtureMigrations = __DIR__.'/Fixtures/migrations';
        if (is_dir($fixtureMigrations)) {
            $this->loadMigrationsFrom($fixtureMigrations);
        }
    }

    /**
     * Vérifie si le test doit être exécuté sur MySQL.
     * À utiliser dans les tests qui dépendent d'un driver spécifique.
     */
    protected function isMySQL(): bool
    {
        return config('database.default') === 'mysql';
    }

    /**
     * Vérifie si le test doit être exécuté sur SQLite.
     */
    protected function isSQLite(): bool
    {
        return config('database.default') === 'sqlite';
    }

    /**
     * Marque le test comme ignoré si le driver n'est pas MySQL.
     */
    protected function requireMySQL(): void
    {
        if (! $this->isMySQL()) {
            $this->markTestSkipped('Ce test nécessite MySQL');
        }
    }

    /**
     * Marque le test comme ignoré si le driver n'est pas SQLite.
     */
    protected function requireSQLite(): void
    {
        if (! $this->isSQLite()) {
            $this->markTestSkipped('Ce test nécessite SQLite');
        }
    }
}

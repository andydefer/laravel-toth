<?php

// tests/Integration/Directives/TothDiscoveryDirectiveTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelToth\Directives\TothDiscoveryDirective;
use AndyDefer\LaravelToth\Tests\Fixtures\CodeSnippets\ConfigSnippets;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\Product;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelToth\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

final class TothDiscoveryDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configPath = config_path('toth.php');

        $this->ensureConfigFileExists();

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $this->service->getKernel()->addDirective(TothDiscoveryDirective::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->service->destroy();
    }

    private function ensureConfigFileExists(): void
    {
        $configDir = dirname($this->configPath);

        if (! File::exists($configDir)) {
            File::makeDirectory($configDir, 0755, true);
        }

        if (! File::exists($this->configPath)) {
            File::put($this->configPath, ConfigSnippets::DEFAULT_CONFIG);
        }
    }

    private function resetConfig(): void
    {
        Config::set('toth.archivables', []);
        File::put($this->configPath, ConfigSnippets::DEFAULT_CONFIG);
    }

    public function test_discover_with_default_source(): void
    {
        $this->resetConfig();

        $response = $this->service->run('toth:discover');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Starting model discovery', $response->output);
        $this->assertStringContainsString('No sources specified, using default: app.Models', $response->output);
    }

    public function test_discover_with_fixtures_source(): void
    {
        $this->resetConfig();

        $response = $this->service->run('toth:discover [tests.Fixtures.Models]');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Found 3 Eloquent models', $response->output);
        $this->assertStringContainsString(TestUser::class, $response->output);
        $this->assertStringContainsString(TestProduct::class, $response->output);
        $this->assertStringContainsString(Product::class, $response->output);

        $archivables = Config::get('toth.archivables', []);
        $this->assertContains(TestUser::class, $archivables);
        $this->assertContains(TestProduct::class, $archivables);
        $this->assertContains(Product::class, $archivables);
    }

    public function test_discover_with_multiple_sources(): void
    {
        $this->resetConfig();

        $response = $this->service->run('toth:discover [tests.Fixtures.Models, app.Models]');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Found', $response->output);
    }

    public function test_discover_with_invalid_source_returns_no_models(): void
    {
        $this->resetConfig();

        $response = $this->service->run('toth:discover [invalid.path]');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No Eloquent models found', $response->output);
    }

    public function test_discover_merges_with_existing_configuration(): void
    {
        $this->resetConfig();
        Config::set('toth.archivables', ['App\\Models\\ExistingModel']);

        $response = $this->service->run('toth:discover [tests.Fixtures.Models]');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $archivables = Config::get('toth.archivables', []);
        $this->assertContains('App\\Models\\ExistingModel', $archivables);
        $this->assertContains(TestUser::class, $archivables);
        $this->assertContains(TestProduct::class, $archivables);
        $this->assertContains(Product::class, $archivables);
    }

    public function test_discover_with_alias(): void
    {
        $this->resetConfig();

        $response = $this->service->run('discover [tests.Fixtures.Models]');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Found 3 Eloquent models', $response->output);
    }

    public function test_discover_with_scan_alias(): void
    {
        $this->resetConfig();

        $response = $this->service->run('scan [tests.Fixtures.Models]');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Found 3 Eloquent models', $response->output);
    }

    public function test_discover_when_config_file_missing(): void
    {
        $this->resetConfig();

        if (File::exists($this->configPath)) {
            File::delete($this->configPath);
        }

        $response = $this->service->run('toth:discover [tests.Fixtures.Models]');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Configuration file not found', $response->output);
        $this->assertStringContainsString('Run: php artisan vendor:publish --tag=toth-config', $response->output);
    }
}

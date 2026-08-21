<?php

// tests/Integration/Services/DiscoveryServiceTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Services;

use AndyDefer\LaravelToth\Contracts\Services\DiscoveryServiceInterface;
use AndyDefer\LaravelToth\Services\DiscoveryService;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\Product;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelToth\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelToth\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use PhpParser\Parser;

final class DiscoveryServiceTest extends IntegrationTestCase
{
    private DiscoveryServiceInterface $discoveryService;

    protected function setUp(): void
    {
        parent::setUp();

        $fileSystem = $this->app->make(FileSystemInterface::class);
        $parser = $this->app->make(Parser::class);

        $this->discoveryService = new DiscoveryService($fileSystem, $parser);
    }

    public function test_discover_models_from_single_source(): void
    {
        $source = 'tests/Fixtures/Models';

        $models = $this->discoveryService->discoverModels([$source]);

        $this->assertNotEmpty($models);
        $this->assertContains(TestUser::class, $models);
        $this->assertContains(TestProduct::class, $models);
        $this->assertContains(Product::class, $models);
    }

    public function test_discover_models_from_multiple_sources(): void
    {
        $sources = [
            'tests/Fixtures/Models',
        ];

        $models = $this->discoveryService->discoverModels($sources);

        $this->assertNotEmpty($models);
        $this->assertCount(3, $models);
    }

    public function test_discover_models_returns_unique_results(): void
    {
        $sources = [
            'tests/Fixtures/Models',
            'tests/Fixtures/Models',
        ];

        $models = $this->discoveryService->discoverModels($sources);

        $this->assertCount(3, $models);
    }

    public function test_discover_models_with_invalid_source_returns_empty(): void
    {
        $sources = ['invalid/path'];

        $models = $this->discoveryService->discoverModels($sources);

        $this->assertEmpty($models);
    }

    public function test_discover_models_finds_only_eloquent_models(): void
    {
        $source = 'tests/Fixtures/Models';

        $models = $this->discoveryService->discoverModels([$source]);

        $this->assertContains(TestUser::class, $models);
        $this->assertContains(TestProduct::class, $models);
        $this->assertContains(Product::class, $models);
    }

    public function test_discover_models_with_deep_directory_structure(): void
    {
        $source = 'tests/Fixtures';

        $models = $this->discoveryService->discoverModels([$source]);

        $this->assertNotEmpty($models);
        $this->assertContains(TestUser::class, $models);
        $this->assertContains(TestProduct::class, $models);
        $this->assertContains(Product::class, $models);
    }
}

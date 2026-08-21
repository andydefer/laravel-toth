<?php

// tests/Integration/Services/DiscoveryServiceTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Integration\Services;

use AndyDefer\LaravelToth\Contracts\Services\DiscoveryServiceInterface;
use AndyDefer\LaravelToth\Services\DiscoveryService;
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
        // Arrange - chemin depuis la racine du projet
        $source = 'tests/Fixtures/Models';

        // Act
        $models = $this->discoveryService->discoverModels([$source]);

        // Assert
        $this->assertNotEmpty($models);
        $this->assertContains(TestUser::class, $models);
        $this->assertContains(TestProduct::class, $models);
    }

    public function test_discover_models_from_multiple_sources(): void
    {
        // Arrange
        $sources = [
            'tests/Fixtures/Models',
        ];

        // Act
        $models = $this->discoveryService->discoverModels($sources);

        // Assert
        $this->assertNotEmpty($models);
        $this->assertCount(2, $models);
    }

    public function test_discover_models_returns_unique_results(): void
    {
        // Arrange
        $sources = [
            'tests/Fixtures/Models',
            'tests/Fixtures/Models',
        ];

        // Act
        $models = $this->discoveryService->discoverModels($sources);

        // Assert
        $this->assertCount(2, $models);
    }

    public function test_discover_models_with_invalid_source_returns_empty(): void
    {
        // Arrange
        $sources = ['invalid/path'];

        // Act
        $models = $this->discoveryService->discoverModels($sources);

        // Assert
        $this->assertEmpty($models);
    }

    public function test_discover_models_finds_only_eloquent_models(): void
    {
        // Arrange
        $source = 'tests/Fixtures/Models';

        // Act
        $models = $this->discoveryService->discoverModels([$source]);

        // Assert
        $this->assertContains(TestUser::class, $models);
        $this->assertContains(TestProduct::class, $models);
    }

    public function test_discover_models_with_deep_directory_structure(): void
    {
        // Arrange
        $source = 'tests/Fixtures';

        // Act
        $models = $this->discoveryService->discoverModels([$source]);

        // Assert
        $this->assertNotEmpty($models);
        $this->assertContains(TestUser::class, $models);
        $this->assertContains(TestProduct::class, $models);
    }
}

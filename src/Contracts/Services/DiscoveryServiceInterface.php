<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Contracts\Services;

/**
 * Contract for the model discovery service.
 *
 * Defines the interface for discovering Eloquent models in the application
 * using AST parsing to detect classes that extend Illuminate\Database\Eloquent\Model.
 */
interface DiscoveryServiceInterface
{
    /**
     * Discovers all Eloquent models in the given directories.
     *
     * @param  array<string>  $sources  Directories to scan (dot notation, e.g., 'app.Models')
     * @return array<int, string> List of discovered model FQCNs
     */
    public function discoverModels(array $sources): array;
}

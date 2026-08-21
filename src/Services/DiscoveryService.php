<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Services;

use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\LaravelToth\Contracts\Services\DiscoveryServiceInterface;
use AndyDefer\LaravelToth\Services\Visitors\ModelDiscoveryVisitor;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;

/**
 * Service responsible for discovering Eloquent models in the filesystem.
 *
 * Scans directories recursively, parses PHP files using AST analysis,
 * and identifies classes that extend Illuminate\Database\Eloquent\Model.
 */
final class DiscoveryService implements DiscoveryServiceInterface
{
    private const MAX_SCAN_DEPTH = 4;

    public function __construct(
        private readonly FileSystemInterface $fileSystem,
        private readonly Parser $parser,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function discoverModels(array $sources): array
    {
        $models = [];

        foreach ($sources as $source) {
            $directory = $this->resolvePath($source);

            if (! $this->fileSystem->isDirectory($directory)) {
                continue;
            }

            $models = array_merge($models, $this->scanDirectory($directory));
        }

        return array_unique($models);
    }

    /**
     * Resolves a source path to an absolute filesystem path.
     *
     * Supports dot notation (app.Models -> app/Models) and % notation
     * for navigating up directories from the project root.
     */
    private function resolvePath(string $source): string
    {
        $projectRoot = Paths::projectRoot();

        if (str_starts_with($source, '%')) {
            return $this->resolveRelativePath($source, $projectRoot);
        }

        $path = str_replace('.', DIRECTORY_SEPARATOR, $source);

        return $projectRoot.DIRECTORY_SEPARATOR.$path;
    }

    /**
     * Resolves a path with % notation for moving up directories.
     */
    private function resolveRelativePath(string $source, string $projectRoot): string
    {
        $count = 0;
        $temp = $source;

        while (str_starts_with($temp, '%')) {
            $count++;
            $temp = substr($temp, 1);
        }

        $relativePath = str_replace('.', DIRECTORY_SEPARATOR, $temp);
        $prefix = str_repeat('..'.DIRECTORY_SEPARATOR, $count);

        return $projectRoot.DIRECTORY_SEPARATOR.$prefix.$relativePath;
    }

    /**
     * Scans a directory recursively for Eloquent models.
     */
    private function scanDirectory(string $directory, int $maxDepth = self::MAX_SCAN_DEPTH): array
    {
        $models = [];

        if (! $this->fileSystem->isDirectory($directory)) {
            return $models;
        }

        $this->scanDirectoryRecursive($directory, $models, 0, $maxDepth);

        return $models;
    }

    /**
     * Recursively scans a directory and collects model FQCNs.
     */
    private function scanDirectoryRecursive(string $directory, array &$models, int $currentDepth, int $maxDepth): void
    {
        if ($currentDepth > $maxDepth) {
            return;
        }

        $files = $this->fileSystem->glob($directory.'/*.php');

        foreach ($files as $file) {
            if (! $this->fileSystem->isFile($file)) {
                continue;
            }

            try {
                $content = $this->fileSystem->get($file);
                $found = $this->extractModelsFromFile($content);
                $models = array_merge($models, $found);
            } catch (\Throwable $e) {
                continue;
            }
        }

        $subDirectories = $this->fileSystem->glob($directory.'/*', GLOB_ONLYDIR);

        foreach ($subDirectories as $subDirectory) {
            $this->scanDirectoryRecursive($subDirectory, $models, $currentDepth + 1, $maxDepth);
        }
    }

    /**
     * Extracts Eloquent model FQCNs from a PHP file content.
     *
     * @return array<int, string> List of model FQCNs found in the file
     */
    private function extractModelsFromFile(string $content): array
    {
        try {
            $ast = $this->parser->parse($content);

            if ($ast === null) {
                return [];
            }

            $visitor = new ModelDiscoveryVisitor;
            $traverser = new NodeTraverser;
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            return $visitor->getModels();
        } catch (Error $e) {
            return [];
        }
    }
}

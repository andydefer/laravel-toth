<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelToth\Contracts\Services\DiscoveryServiceInterface;

/**
 * CLI command to discover and register all Eloquent models as archivable.
 *
 * Scans the given directories for Eloquent models and adds them to the
 * 'archivables' configuration key in toth.php.
 *
 * @example
 * bin/task toth:discover [%%tests.Models, %%src.Domain]  // Scan multiple directories
 * bin/task toth:discover                                 // Default: app.Models
 */
final class TothDiscoveryDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'toth:discover {sources*}#"Directories to scan (e.g., %%tests.Models, app.Models)"';
    }

    public function getDescription(): string
    {
        return 'Discover and register Eloquent models as archivable in the configuration.';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['discover', 'scan']);
    }

    protected function execute(): ExitCode
    {
        $this->info('🔍 Starting model discovery...');

        $sources = $this->getSourcesFromInput();
        $discoveryService = $this->getApplication()->make(DiscoveryServiceInterface::class);

        $models = $discoveryService->discoverModels($sources);

        if (empty($models)) {
            $this->getConsole()->alertWarning('No Eloquent models found in the specified directories.');

            return ExitCode::SUCCESS;
        }

        $this->info(sprintf('📋 Found %d Eloquent models:', count($models)));

        foreach ($models as $model) {
            $this->line(sprintf('  - %s', $model));
        }

        $config = $this->getApplication()->make('config');
        $currentArchivables = $config->get('toth.archivables', []);

        $merged = array_unique(array_merge($currentArchivables, $models));
        sort($merged);

        $config->set('toth.archivables', $merged);

        $this->saveConfiguration($merged);

        $this->newLine();
        $this->info(sprintf('✅ Added %d models to archivables configuration.', count($models)));

        return ExitCode::SUCCESS;
    }

    private function getSourcesFromInput(): array
    {
        $sources = $this->getVariadic('sources');

        if (! empty($sources)) {
            return $sources;
        }

        $this->info('📋 No sources specified, using default: app.Models');

        return ['app.Models'];
    }

    private function saveConfiguration(array $archivables): void
    {
        $configPath = config_path('toth.php');

        if (! file_exists($configPath)) {
            $this->getConsole()->alertWarning(' Configuration file not found. Please publish the config first.');
            $this->info('   Run: php artisan vendor:publish --tag=toth-config');

            return;
        }

        $content = file_get_contents($configPath);

        $formatted = array_map(fn ($class) => $class.'::class', $archivables);

        $pattern = "/'archivables'\s*=>\s*\[.*?\],/s";
        $replacement = "'archivables' => [\n        ".implode(",\n        ", $formatted).",\n    ],";

        $newContent = preg_replace($pattern, $replacement, $content);

        if ($newContent !== null) {
            file_put_contents($configPath, $newContent);
            $this->info('✅ Configuration file updated successfully.');
        } else {
            $this->error('❌ Failed to update configuration file.');
        }
    }
}

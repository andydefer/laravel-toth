<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Utils;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\ConsoleWriter\Console\Services\VirtualTerminalService;

/**
 * Utility class for managing progress bars in the console.
 *
 * Provides a clean interface for displaying and updating progress bars
 * with throttling to avoid excessive rendering.
 */
final class ProgressManager
{
    private const BAR_WIDTH = 40;

    private const COMPLETE_CHAR = '█';

    private const EMPTY_CHAR = '░';

    private const RENDER_INTERVAL = 300000; // 600ms in microseconds

    private VirtualTerminalService $vt;

    private float $lastRenderTime = 0;

    private int $total = 0;

    private int $current = 0;

    private string $label = '';

    private string $detail = '';

    private bool $isActive = false;

    public function __construct(
        private readonly Console $console,
    ) {
        $this->vt = new VirtualTerminalService($console->getAnsiConverter());
    }

    /**
     * Starts a new progress bar.
     *
     * @param  string  $label  The label to display
     * @param  int  $total  The total number of items to process
     */
    public function start(string $label, int $total): void
    {
        $this->total = $total;
        $this->current = 0;
        $this->label = $label;
        $this->detail = '';
        $this->isActive = true;
        $this->lastRenderTime = 0;

        $this->vt->clear();
        $this->vt->add('label', "{$label}...");
        $this->vt->add('progress', $this->buildProgressBar(0, $total));
        $this->vt->add('detail', '');
        $this->vt->add('count', "0 / {$total}");
        $this->vt->render();
        $this->lastRenderTime = microtime(true) * 1000000;
    }

    /**
     * Updates the progress bar.
     *
     * @param  int  $current  The current progress
     * @param  string  $detail  Optional detail to display
     */
    public function update(int $current, string $detail = ''): void
    {
        if (! $this->isActive) {
            return;
        }

        $this->current = $current;
        $this->detail = $detail;

        $this->vt->update('progress', $this->buildProgressBar($current, $this->total));
        $this->vt->update('detail', "   {$detail}");
        $this->vt->update('count', "   {$current} / {$this->total}");

        $this->renderWithThrottle();
    }

    /**
     * Increments the progress by one.
     *
     * @param  string  $detail  Optional detail to display
     */
    public function advance(string $detail = ''): void
    {
        $this->update($this->current + 1, $detail);
    }

    /**
     * Finishes the progress bar.
     *
     * @param  string  $message  The completion message
     */
    public function finish(string $message): void
    {
        if (! $this->isActive) {
            return;
        }

        $this->current = $this->total;

        $this->vt->update('progress', $this->buildProgressBar($this->total, $this->total));
        $this->vt->remove('detail');
        $this->vt->remove('count');
        $this->vt->update('label', $message);
        $this->vt->render();
        $this->lastRenderTime = microtime(true) * 1000000;

        $this->console->newLine();
        $this->isActive = false;
    }

    /**
     * Checks if the progress bar is currently active.
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Gets the current progress.
     */
    public function getProgress(): int
    {
        return $this->current;
    }

    /**
     * Gets the total.
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Builds the progress bar string.
     */
    private function buildProgressBar(int $current, int $total): string
    {
        $percentage = $total > 0 ? ($current / $total) * 100 : 0;
        $filled = (int) round(self::BAR_WIDTH * ($current / max($total, 1)));

        $bar = '['
            .str_repeat(self::COMPLETE_CHAR, $filled)
            .str_repeat(self::EMPTY_CHAR, self::BAR_WIDTH - $filled)
            .']';

        return $bar.' '.number_format($percentage, 0).'%';
    }

    /**
     * Renders the progress bar with throttling.
     */
    private function renderWithThrottle(): void
    {
        $now = microtime(true) * 1000000;
        if ($now - $this->lastRenderTime >= self::RENDER_INTERVAL) {
            $this->vt->render();
            $this->lastRenderTime = $now;
        }
    }
}

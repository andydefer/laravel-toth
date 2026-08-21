<?php

// tests/Unit/Utils/ProgressManagerTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Unit\Utils;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\LaravelToth\Tests\IntegrationTestCase;
use AndyDefer\LaravelToth\Utils\ProgressManager;

final class ProgressManagerTest extends IntegrationTestCase
{
    private ProgressManager $progressManager;

    private Console $console;

    private int $originalOutputBufferingLevel;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Démarrer l'obfuscation de la sortie
        $this->startOutputBuffering();

        $this->console = new Console;
        $this->progressManager = new ProgressManager($this->console);
    }

    protected function tearDown(): void
    {
        // ✅ Capturer la sortie
        $this->captureOutput();

        parent::tearDown();
    }

    /**
     * Démarre l'obfuscation de la sortie pour les barres de progression.
     */
    private function startOutputBuffering(): void
    {
        $this->originalOutputBufferingLevel = ob_get_level();

        if (! ob_get_level()) {
            ob_start();
        }
    }

    /**
     * Capture et supprime la sortie des barres de progression.
     */
    private function captureOutput(): void
    {
        if (ob_get_level() > $this->originalOutputBufferingLevel) {
            ob_end_clean();
        } elseif (ob_get_level() > 0) {
            ob_clean();
        }
    }

    public function test_start_initializes_progress_bar(): void
    {
        // Arrange
        $label = 'Test Progress';
        $total = 10;

        // Act
        $this->progressManager->start($label, $total);

        // Assert
        $this->assertTrue($this->progressManager->isActive());
        $this->assertSame(0, $this->progressManager->getProgress());
        $this->assertSame(10, $this->progressManager->getTotal());
    }

    public function test_update_changes_progress(): void
    {
        // Arrange
        $this->progressManager->start('Test', 10);

        // Act
        $this->progressManager->update(5, 'Processing item 5');

        // Assert
        $this->assertTrue($this->progressManager->isActive());
        $this->assertSame(5, $this->progressManager->getProgress());
    }

    public function test_advance_increments_progress_by_one(): void
    {
        // Arrange
        $this->progressManager->start('Test', 10);

        // Act
        $this->progressManager->advance('Processing item 1');
        $this->progressManager->advance('Processing item 2');

        // Assert
        $this->assertSame(2, $this->progressManager->getProgress());
    }

    public function test_finish_completes_progress_bar(): void
    {
        // Arrange
        $this->progressManager->start('Test', 10);
        $this->progressManager->update(10);

        // Act
        $this->progressManager->finish('✅ Completed');

        // Assert
        $this->assertFalse($this->progressManager->isActive());
        $this->assertSame(10, $this->progressManager->getProgress());
    }

    public function test_update_does_nothing_when_inactive(): void
    {
        // Arrange
        $this->progressManager->start('Test', 10);

        // ✅ Le progrès est à 0
        $this->assertSame(0, $this->progressManager->getProgress());

        // ✅ Finir la barre
        $this->progressManager->finish('Done');

        // ✅ Vérifier que le progrès est à 10 (complet)
        $this->assertSame(10, $this->progressManager->getProgress());

        // Act - Tenter de mettre à jour après finish
        $this->progressManager->update(5, 'Should not update');

        // Assert - Le progrès reste à 10
        $this->assertFalse($this->progressManager->isActive());
        $this->assertSame(10, $this->progressManager->getProgress());
    }

    public function test_advance_does_nothing_when_inactive(): void
    {
        // Arrange
        $this->progressManager->start('Test', 10);

        // ✅ Le progrès est à 0
        $this->assertSame(0, $this->progressManager->getProgress());

        // ✅ Finir la barre
        $this->progressManager->finish('Done');

        // ✅ Vérifier que le progrès est à 10 (complet)
        $this->assertSame(10, $this->progressManager->getProgress());

        // Act - Tenter d'avancer après finish
        $this->progressManager->advance('Should not advance');

        // Assert - Le progrès reste à 10
        $this->assertFalse($this->progressManager->isActive());
        $this->assertSame(10, $this->progressManager->getProgress());
    }

    public function test_get_progress_returns_current_value(): void
    {
        // Arrange
        $this->progressManager->start('Test', 100);

        // Act
        $this->progressManager->update(42);

        // Assert
        $this->assertSame(42, $this->progressManager->getProgress());
    }

    public function test_get_total_returns_total_value(): void
    {
        // Arrange
        $total = 50;

        // Act
        $this->progressManager->start('Test', $total);

        // Assert
        $this->assertSame(50, $this->progressManager->getTotal());
    }

    public function test_is_active_returns_correct_state(): void
    {
        // Arrange
        $this->assertFalse($this->progressManager->isActive());

        // Act
        $this->progressManager->start('Test', 10);

        // Assert
        $this->assertTrue($this->progressManager->isActive());

        // Act
        $this->progressManager->finish('Done');

        // Assert
        $this->assertFalse($this->progressManager->isActive());
    }

    public function test_multiple_start_finish_cycles(): void
    {
        // First cycle
        $this->progressManager->start('First', 5);
        $this->assertTrue($this->progressManager->isActive());
        $this->progressManager->update(3);
        $this->assertSame(3, $this->progressManager->getProgress());
        $this->progressManager->finish('First done');
        $this->assertFalse($this->progressManager->isActive());

        // Second cycle
        $this->progressManager->start('Second', 10);
        $this->assertTrue($this->progressManager->isActive());
        $this->progressManager->update(7);
        $this->assertSame(7, $this->progressManager->getProgress());
        $this->progressManager->finish('Second done');
        $this->assertFalse($this->progressManager->isActive());
    }

    public function test_progress_bar_string_format(): void
    {
        // Arrange
        $this->progressManager->start('Test', 100);

        // Act
        $this->progressManager->update(50);

        // Assert
        $this->assertSame(50, $this->progressManager->getProgress());

        // Act
        $this->progressManager->update(100);

        // Assert
        $this->assertSame(100, $this->progressManager->getProgress());
    }

    public function test_finish_without_start_does_nothing(): void
    {
        // Arrange
        $this->assertFalse($this->progressManager->isActive());

        // Act
        $this->progressManager->finish('Should not work');

        // Assert
        $this->assertFalse($this->progressManager->isActive());
        $this->assertSame(0, $this->progressManager->getProgress());
        $this->assertSame(0, $this->progressManager->getTotal());
    }

    public function test_update_without_start_does_nothing(): void
    {
        // Arrange
        $this->assertFalse($this->progressManager->isActive());

        // Act
        $this->progressManager->update(50, 'Should not work');

        // Assert
        $this->assertFalse($this->progressManager->isActive());
        $this->assertSame(0, $this->progressManager->getProgress());
    }

    public function test_advance_without_start_does_nothing(): void
    {
        // Arrange
        $this->assertFalse($this->progressManager->isActive());

        // Act
        $this->progressManager->advance('Should not work');

        // Assert
        $this->assertFalse($this->progressManager->isActive());
        $this->assertSame(0, $this->progressManager->getProgress());
    }

    public function test_progress_with_large_numbers(): void
    {
        // Arrange
        $total = 1000000;

        // Act
        $this->progressManager->start('Large', $total);
        $this->progressManager->update(500000);

        // Assert
        $this->assertSame(500000, $this->progressManager->getProgress());
        $this->assertSame(1000000, $this->progressManager->getTotal());

        // Act
        $this->progressManager->finish('Done');

        // Assert
        $this->assertFalse($this->progressManager->isActive());
    }

    public function test_progress_with_detail_updates(): void
    {
        // Arrange
        $detail = 'Processing item 42';

        // Act
        $this->progressManager->start('Test', 100);
        $this->progressManager->update(42, $detail);

        // Assert
        $this->assertSame(42, $this->progressManager->getProgress());

        // Act
        $this->progressManager->advance('Processing item 43');

        // Assert
        $this->assertSame(43, $this->progressManager->getProgress());
    }
}

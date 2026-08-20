<?php

// database/migrations/2024_01_01_000001_create_archives_table.php

namespace AndyDefer\LaravelToth\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archives', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 255);
            $table->unsignedBigInteger('row_id');
            $table->json('data');
            $table->timestamp('last_save_at');
            $table->timestamps();

            // Index pour recherches rapides
            $table->index(['table_name', 'row_id'], 'idx_table_row');
            $table->index('last_save_at', 'idx_last_save');

            // Index composite pour les requêtes fréquentes
            $table->index(['table_name', 'row_id', 'last_save_at'], 'idx_table_row_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archives');
    }
};

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
            $table->string('table_name');
            $table->string('row_id');
            $table->string('model_class');
            $table->json('data');
            $table->timestamp('last_save_at');
            $table->timestamps();

            $table->index(['table_name', 'row_id']);
            $table->index('model_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archives');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Timed transcript lines. Populated in v2; the schema is ready now. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oral_history_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oral_history_id')->constrained('oral_histories')->cascadeOnDelete();
            $table->unsignedInteger('start_ms');
            $table->unsignedInteger('end_ms');
            $table->string('speaker', 120)->nullable();
            $table->text('text')->nullable();
            $table->text('translation')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->timestamps();

            $table->index(['oral_history_id', 'start_ms'], 'idx_ohs_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oral_history_segments');
    }
};

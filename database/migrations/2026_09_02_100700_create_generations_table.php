<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Human labels for generations. Purely captions: nothing in the traversal
 * engine depends on these, so a missing or wrong generation number degrades a
 * label and never the tree. Real depth comes from lineage_depths.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->foreignId('tribe_id')->constrained('tribes')->restrictOnDelete();
            $table->foreignId('clan_id')->nullable()->constrained('clans')->nullOnDelete();

            $table->smallInteger('generation_number');
            $table->string('generation_name', 100)->nullable();
            $table->string('local_name', 150)->nullable();
            $table->text('description')->nullable();
            $table->smallInteger('estimated_start_year')->nullable();
            $table->smallInteger('estimated_end_year')->nullable();

            $table->timestamps();

            $table->unique(['tribe_id', 'clan_id', 'generation_number'], 'uq_generations');
            $table->index(['tribe_id', 'generation_number'], 'idx_generations_tribe_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secondary tribe/clan affiliations, for mixed-marriage lineages.
 *
 * people.tribe_id remains the primary affiliation used on the scoping hot path;
 * this table records the others without widening that index. Enabled by
 * config('genealogy.multi_tribe').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_affiliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('tribe_id')->constrained('tribes')->cascadeOnDelete();
            $table->foreignId('clan_id')->nullable()->constrained('clans')->nullOnDelete();
            $table->string('note', 255)->nullable();   // e.g. "through mother's line"
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['person_id', 'tribe_id', 'clan_id'], 'uq_person_affiliation');
            $table->index(['tribe_id', 'clan_id'], 'idx_affiliation_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_affiliations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a clan begins.
 *
 * Family branches have named their apical ancestor since they existed; clans
 * never could, so "the Guite clan descends from Guite" was something the
 * archive had no way to record. Nullable, because a clan's name usually
 * outlives any certainty about the person it came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->foreignId('ancestor_person_id')
                ->nullable()
                ->after('description')
                ->constrained('people')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ancestor_person_id');
        });
    }
};

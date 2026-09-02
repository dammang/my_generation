<?php

use App\Enums\MatchKeyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The blocking layer that makes duplicate detection O(n·k) instead of O(n²).
 *
 * Two people are compared only if they share a key. idx_pmk_lookup is the index
 * that matters: candidates are found by self-joining within a block, never by
 * comparing all pairs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_match_keys', function (Blueprint $table) {
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->enum('key_type', MatchKeyType::values());
            $table->string('key_value', 120);

            $table->primary(['person_id', 'key_type', 'key_value'], 'pk_person_match_keys');
            $table->index(['key_type', 'key_value'], 'idx_pmk_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_match_keys');
    }
};

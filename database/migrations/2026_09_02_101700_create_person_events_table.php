<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The chronicle. One ordered query per person yields both the timeline and,
 * for migration events, the polyline for a future map.
 *
 * Migration is modelled here with from_place_id / to_place_id rather than in a
 * separate table — a person's migrations belong on their timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_events', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('event_type_id')->constrained('event_types')->restrictOnDelete();
            $table->foreignId('union_id')->nullable()->constrained('unions')->nullOnDelete();

            $table->string('title', 191)->nullable();
            $table->text('description')->nullable();

            $table->uncertainDate('event');
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->foreignId('from_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->foreignId('to_place_id')->nullable()->constrained('places')->nullOnDelete();

            $table->privacyLevel();   // nullable: inherit from the person
            $table->verificationStatus();

            $table->contributable();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->index(['person_id', 'event_year', 'event_date'], 'idx_pe_person_date');
            $table->index('event_type_id', 'idx_pe_type');
            $table->index('place_id', 'idx_pe_place');
            $table->index(['from_place_id', 'to_place_id'], 'idx_pe_migration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_events');
    }
};

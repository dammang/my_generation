<?php

use App\Enums\Certainty;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a source to any fact — and, crucially, to a specific field.
 *
 * Field-level citation is what lets a dispute be settled by comparing the
 * evidence for `birth_date` specifically, rather than for a whole person record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->morphs('citable');   // person, relationship, union, person_event, person_name, story
            $table->string('field', 60)->nullable();   // NULL = the whole record
            $table->string('page_or_locator', 120)->nullable();
            $table->text('quote')->nullable();
            $table->enum('confidence', Certainty::values())->default(Certainty::Probable->value);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_id', 'citable_type', 'citable_id', 'field'], 'uq_citation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citations');
    }
};

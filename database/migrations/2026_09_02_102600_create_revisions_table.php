<?php

use App\Enums\RevisionAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The field-level genealogical ledger. Immutable: no updated_at, no soft delete.
 *
 * Values are JSON so a date, an enum and a foreign key id all round-trip
 * losslessly. Written by the RecordsRevisions trait via a model observer, so it
 * cannot be forgotten at a call site.
 *
 * This is the fastest-growing table in the schema. Partitioning by
 * YEAR(created_at) is deliberately deferred to Phase 15, once row counts are
 * measurable — partitioning in MySQL requires every unique key to contain the
 * partition column, which would mean dropping the surrogate primary key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('revisionable');
            $table->string('field', 60)->nullable();   // NULL for whole-record create/delete
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->enum('action', RevisionAction::values());
            $table->string('reason', 500)->nullable();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('change_request_id')->nullable()->constrained('change_requests')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('ip_hash', 64)->nullable();   // hashed, never raw
            $table->timestamp('created_at')->useCurrent();

            $table->index(['revisionable_type', 'revisionable_id', 'created_at'], 'idx_rev_target');
            $table->index(['changed_by', 'created_at'], 'idx_rev_user');
            $table->index('created_at', 'idx_rev_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};

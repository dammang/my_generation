<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merges are reversible.
 *
 * loser_snapshot holds the full record before the merge; moved_records logs
 * every foreign key repointed, by table and id, so reversal replays it
 * backwards. The loser row itself survives soft-deleted with
 * merged_into_person_id set, so old ULIDs and share links still resolve — they
 * redirect to the winner rather than 404.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_merges', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->foreignId('winner_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('loser_person_id')->constrained('people')->restrictOnDelete();

            $table->json('field_choices');
            $table->json('moved_records');
            $table->json('loser_snapshot');

            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at')->useCurrent();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reverted_at')->nullable();

            $table->index('winner_person_id', 'idx_merge_winner');
            $table->index('loser_person_id', 'idx_merge_loser');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_merges');
    }
};

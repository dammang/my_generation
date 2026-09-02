<?php

use App\Enums\DuplicateStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_candidates', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->foreignId('person_a_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('person_b_id')->constrained('people')->cascadeOnDelete();

            $table->decimal('score', 4, 3);
            // Per-feature contributions, so a reviewer can see WHY a pair scored
            // as it did rather than being asked to trust a number.
            $table->json('signals')->nullable();

            $table->enum('status', DuplicateStatus::values())->default(DuplicateStatus::Open->value);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['person_a_id', 'person_b_id'], 'uq_dup_pair');
            $table->index(['status', 'score'], 'idx_dup_status_score');
        });

        DB::statement(
            'ALTER TABLE duplicate_candidates ADD CONSTRAINT chk_dup_pair_order
             CHECK (person_a_id < person_b_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_candidates');
    }
};

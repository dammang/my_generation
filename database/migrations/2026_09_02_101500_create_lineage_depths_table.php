<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "17th Generation" without a closure table.
 *
 * Computed only for designated apical ancestors — family_branches.ancestor_person_id
 * and tribe/clan founders — typically a few hundred roots, not millions. A full
 * closure over all people would be hundreds of millions of rows with
 * catastrophic write amplification; this is bounded at
 * roots × descendants-of-that-root.
 *
 * min/max depth differ under pedigree collapse (cousins marrying, common in
 * small clans): a person can be 14 generations from the founder down one line
 * and 16 down another. The UI shows the range rather than inventing one answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lineage_depths', function (Blueprint $table) {
            $table->foreignId('root_person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->unsignedSmallInteger('depth');
            $table->unsignedSmallInteger('min_depth');
            $table->unsignedSmallInteger('max_depth');
            $table->unsignedInteger('path_count')->default(1);
            $table->timestamp('computed_at')->nullable();

            $table->primary(['root_person_id', 'person_id'], 'pk_lineage_depths');
            $table->index('person_id', 'idx_ld_person');
            $table->index(['root_person_id', 'depth'], 'idx_ld_root_depth');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineage_depths');
    }
};

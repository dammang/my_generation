<?php

use App\Enums\UnionStatus;
use App\Enums\UnionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marriages and partnerships.
 *
 * A partnership is an entity, not an edge: it has its own date, place, type and
 * end state, and its own children. That is why spouses are NOT rows in
 * `relationships`.
 *
 * partner_1_id is always the lower internal id. Normalising the pair is what
 * makes the unique key actually prevent the same marriage being entered twice
 * from either spouse's screen. partner_2_id is nullable because single-parent
 * families are real and common in historical records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unions', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->foreignId('partner_1_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('partner_2_id')->nullable()->constrained('people')->restrictOnDelete();

            $table->enum('union_type', UnionType::values())->default(UnionType::Marriage->value);
            $table->enum('status', UnionStatus::values())->default(UnionStatus::Unknown->value);

            $table->uncertainDate('marriage');
            $table->foreignId('marriage_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->date('separation_date')->nullable();
            $table->date('divorce_date')->nullable();

            $table->unsignedTinyInteger('order_index')->default(1);   // 1st marriage, 2nd…
            $table->unsignedSmallInteger('children_count')->default(0);

            $table->verificationStatus();
            $table->text('notes')->nullable();

            $table->contributable();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->unique(
                ['partner_1_id', 'partner_2_id', 'union_type', 'order_index', 'deleted_token'],
                'uq_union_pair'
            );
            $table->index('partner_1_id', 'idx_union_p1');
            $table->index('partner_2_id', 'idx_union_p2');
            $table->index('marriage_date', 'idx_union_marriage_date');
        });

        DB::statement(
            'ALTER TABLE unions ADD CONSTRAINT chk_union_partner_order
             CHECK (partner_2_id IS NULL OR partner_1_id < partner_2_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('unions');
    }
};

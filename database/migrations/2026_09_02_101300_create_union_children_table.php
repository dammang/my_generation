<?php

use App\Enums\ChildRelationshipType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups a child under a couple, for chart layout and birth order.
 *
 * This table does NOT assert parentage. The parentage facts are the rows in
 * `relationships` (father→child, mother→child); AddChildToUnion writes all
 * three in one transaction and stamps relationships.union_id. A nightly
 * consistency job reports any row here with no corresponding parent edge.
 *
 * It earns its place because it is what turns the graph back into the classic
 * chart: husband ═ wife, one drop line, children in birth order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('union_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('union_id')->constrained('unions')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->enum('relationship_type', ChildRelationshipType::values())
                ->default(ChildRelationshipType::Biological->value);
            $table->unsignedSmallInteger('birth_order')->nullable();
            $table->timestamps();

            $table->unique(['union_id', 'person_id'], 'uq_union_child');
            $table->index('person_id', 'idx_uc_person');
            $table->index(['union_id', 'birth_order'], 'idx_uc_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('union_children');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Derived traversal adjacency — a cache, not truth.
 *
 * Every tree CTE reads only this table. `relationships` carries ~25 columns,
 * soft deletes and status filters; dragging that through a recursive CTE at
 * depth 8 is the difference between 80ms and seconds. Here both indexes are
 * covering, so traversal never touches clustered row data.
 *
 * At 1M people this is roughly 2.4M rows / 60MB, small enough for both indexes
 * to stay resident in the buffer pool. Rebuildable at any time with
 * `php artisan genealogy:rebuild-edges`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_edges', function (Blueprint $table) {
            $table->foreignId('parent_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('people')->cascadeOnDelete();
            $table->unsignedTinyInteger('edge_kind')->default(1);   // App\Enums\EdgeKind
            $table->unsignedBigInteger('tribe_id')->nullable();     // denormalised for scoping
            $table->unsignedTinyInteger('confidence')->default(50); // 0-100, from relationships.certainty

            $table->primary(['parent_id', 'child_id', 'edge_kind'], 'pk_family_edges');
            $table->index(['child_id', 'parent_id', 'edge_kind'], 'idx_fe_child');
            $table->index('tribe_id', 'idx_fe_tribe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_edges');
    }
};

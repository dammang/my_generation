<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A named family line, e.g. "Kin Tun Family".
 *
 * ancestor_person_id is the apical ancestor this branch counts generations from
 * — it is the root for lineage_depths, and therefore what makes "17th
 * Generation" computable. The FK is added in the deferred pass, since `people`
 * does not exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_branches', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->foreignId('tribe_id')->constrained('tribes')->restrictOnDelete();
            $table->foreignId('clan_id')->nullable()->constrained('clans')->nullOnDelete();
            $table->unsignedBigInteger('ancestor_person_id')->nullable();

            $table->string('name', 150);
            $table->string('slug', 160);
            $table->string('native_name', 191)->nullable();
            $table->text('description')->nullable();

            $table->foreignId('origin_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->foreignId('current_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->string('current_region', 150)->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();

            $table->unsignedInteger('people_count')->default(0);
            $table->unsignedInteger('generation_count')->default(0);

            $table->recordStatus();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->unique(['tribe_id', 'slug', 'deleted_token'], 'uq_branch_slug');
            $table->index('clan_id', 'idx_branch_clan');
            $table->index('ancestor_person_id', 'idx_branch_ancestor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_branches');
    }
};

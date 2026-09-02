<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The permission spine. One row per tribe, clan and family branch, giving every
 * scoped role assignment a single FK target.
 *
 * `path` is a materialised list of scope ids (/1/14/57/), so "does this user
 * administer this record's scope" is a prefix comparison against the user's
 * cached admin paths — a Tribe Admin automatically has authority in every clan
 * beneath, with no recursive query at request time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scopes', function (Blueprint $table) {
            $table->id();
            $table->string('scopeable_type', 64);
            $table->unsignedBigInteger('scopeable_id');
            $table->foreignId('parent_scope_id')->nullable()->constrained('scopes')->nullOnDelete();
            $table->string('path', 500)->default('');
            $table->unsignedTinyInteger('depth')->default(0);
            $table->timestamps();

            $table->unique(['scopeable_type', 'scopeable_id'], 'uq_scopes_morph');
            $table->index('parent_scope_id', 'idx_scopes_parent');
        });

        DB::statement('CREATE INDEX idx_scopes_path ON scopes (path(191))');
    }

    public function down(): void
    {
        Schema::dropIfExists('scopes');
    }
};

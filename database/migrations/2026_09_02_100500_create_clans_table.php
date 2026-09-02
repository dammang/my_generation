<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clan → sub-clan → branch, to arbitrary depth.
 *
 * No fixed number of hierarchy levels is assumed: `depth` records how deep this
 * row sits and `level_label` carries the tribe's own word for that level
 * ("Sub-clan", "Phung"). Hierarchy shape is data, not schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clans', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->foreignId('tribe_id')->constrained('tribes')->restrictOnDelete();
            $table->foreignId('parent_clan_id')->nullable()->constrained('clans')->nullOnDelete();
            $table->string('path', 500)->default('');
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('level_label', 60)->nullable();

            $table->string('name', 150);
            $table->string('slug', 160);
            $table->string('native_name', 191)->nullable();
            $table->text('description')->nullable();
            $table->mediumText('history')->nullable();

            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();

            $table->unsignedInteger('people_count')->default(0);

            $table->recordStatus();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->unique(['tribe_id', 'slug', 'deleted_token'], 'uq_clans_slug');
            $table->index(['tribe_id', 'parent_clan_id'], 'idx_clans_tribe_parent');
        });

        DB::statement('CREATE INDEX idx_clans_path ON clans (path(191))');
    }

    public function down(): void
    {
        Schema::dropIfExists('clans');
    }
};

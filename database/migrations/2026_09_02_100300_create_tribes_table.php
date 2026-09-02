<?php

use App\Enums\PrivacyLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tribes', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->string('name', 150);
            $table->string('slug', 160);
            $table->string('native_name', 191)->nullable();
            $table->string('short_name', 50)->nullable();
            $table->text('description')->nullable();
            $table->mediumText('history')->nullable();

            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();

            $table->char('country_code', 2)->nullable();
            $table->string('region', 150)->nullable();
            $table->foreignId('primary_place_id')->nullable()->constrained('places')->nullOnDelete();

            $table->privacyLevel('default_privacy_level', PrivacyLevel::Tribe->value);

            $table->unsignedInteger('people_count')->default(0);
            $table->unsignedInteger('clan_count')->default(0);

            // Bumped on any genealogy write within this tribe. Part of every tree
            // cache key, so invalidation is O(1) instead of hunting subtrees.
            $table->unsignedInteger('graph_version')->default(1);

            $table->recordStatus();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->unique(['slug', 'deleted_token'], 'uq_tribes_slug');
            $table->index('country_code', 'idx_tribes_country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tribes');
    }
};

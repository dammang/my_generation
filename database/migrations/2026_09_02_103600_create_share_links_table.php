<?php

use App\Enums\PrivacyLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A share link can never widen visibility beyond max_privacy_level, and living
 * people are masked regardless of what the link permits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();
            $table->char('token', 43)->unique();
            $table->morphs('shareable');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->privacyLevel('max_privacy_level', PrivacyLevel::Public->value);
            $table->unsignedTinyInteger('ancestors')->default(3);
            $table->unsignedTinyInteger('descendants')->default(2);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->index('created_by', 'idx_share_creator');
            $table->index('expires_at', 'idx_share_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_links');
    }
};

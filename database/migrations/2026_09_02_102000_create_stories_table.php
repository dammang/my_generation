<?php

use App\Enums\PrivacyLevel;
use App\Enums\StoryType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->string('title', 255);
            $table->longText('body')->nullable();
            $table->string('summary', 500)->nullable();

            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('family_branch_id')->nullable()->constrained('family_branches')->nullOnDelete();
            $table->foreignId('clan_id')->nullable()->constrained('clans')->nullOnDelete();
            $table->foreignId('tribe_id')->nullable()->constrained('tribes')->nullOnDelete();

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('language', 10)->default('en');
            $table->enum('story_type', StoryType::values())->default(StoryType::Narrative->value);
            $table->smallInteger('era_start_year')->nullable();
            $table->smallInteger('era_end_year')->nullable();

            $table->privacyLevel('visibility', PrivacyLevel::Family->value);
            $table->verificationStatus();
            $table->unsignedInteger('view_count')->default(0);

            $table->contributable();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->index(['tribe_id', 'clan_id', 'family_branch_id'], 'idx_stories_scope');
            $table->index('person_id', 'idx_stories_person');
            $table->index('visibility', 'idx_stories_visibility');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};

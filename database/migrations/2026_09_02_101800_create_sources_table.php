<?php

use App\Enums\PrivacyLevel;
use App\Enums\SourceReliability;
use App\Enums\SourceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->string('title', 255);
            $table->enum('source_type', SourceType::values())->default(SourceType::Other->value);
            $table->text('description')->nullable();
            $table->string('author', 191)->nullable();
            $table->string('publisher', 191)->nullable();
            $table->smallInteger('publication_year')->nullable();
            $table->string('repository', 191)->nullable();
            $table->string('url', 500)->nullable();

            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('informant_person_id')->nullable()->constrained('people')->nullOnDelete();

            $table->enum('reliability', SourceReliability::values())
                ->default(SourceReliability::Secondary->value);

            $table->foreignId('tribe_id')->nullable()->constrained('tribes')->nullOnDelete();
            $table->foreignId('clan_id')->nullable()->constrained('clans')->nullOnDelete();
            $table->privacyLevel('privacy_level', PrivacyLevel::Tribe->value);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->index('source_type', 'idx_sources_type');
            $table->index(['tribe_id', 'clan_id'], 'idx_sources_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};

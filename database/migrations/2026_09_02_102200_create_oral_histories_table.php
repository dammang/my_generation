<?php

use App\Enums\PrivacyLevel;
use App\Enums\TranscriptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recorded interviews with elders.
 *
 * Transcription is NOT implemented in v1, but the columns exist so that turning
 * it on later is a job and a config change, never a migration on a table that
 * by then holds thousands of recordings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oral_histories', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();

            $table->foreignId('interviewee_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('interviewer_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('interviewer_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('recorded_at')->nullable();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->string('language', 10)->default('en');

            $table->enum('transcript_status', TranscriptStatus::values())
                ->default(TranscriptStatus::None->value);
            $table->longText('transcript_text')->nullable();
            $table->string('translation_language', 10)->nullable();
            $table->longText('translation_text')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->privacyLevel('visibility', PrivacyLevel::Family->value);
            $table->verificationStatus();

            $table->contributable();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->index('interviewee_person_id', 'idx_oh_interviewee');
            $table->index('transcript_status', 'idx_oh_transcript');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oral_histories');
    }
};

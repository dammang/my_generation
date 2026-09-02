<?php

use App\Enums\ReviewDecision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Every decision, including intermediate ones. Append-only. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_request_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_request_id')->constrained('change_requests')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('decision', ReviewDecision::values());
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('change_request_id', 'idx_crr_request');
            $table->index('reviewer_id', 'idx_crr_reviewer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_reviews');
    }
};

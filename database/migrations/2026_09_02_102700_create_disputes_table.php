<?php

use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An open disagreement over a fact. Nothing is deleted: both the 1921 and the
 * 1923 birth year survive as claims. A dispute that cannot be settled is a
 * legitimate permanent state — that is honest genealogy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->morphs('disputable');
            $table->string('field', 60)->nullable();

            $table->enum('status', DisputeStatus::values())->default(DisputeStatus::Open->value);
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->enum('resolution', DisputeResolution::values())->nullable();
            $table->text('resolution_note')->nullable();
            $table->unsignedBigInteger('accepted_claim_id')->nullable();

            $table->timestamps();

            $table->index(['disputable_type', 'disputable_id', 'status'], 'idx_disputes_target');
            $table->index('status', 'idx_disputes_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};

<?php

use App\Enums\ClaimStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "This person is me."
 *
 * A claim never auto-approves. ApproveProfileClaim additionally requires that
 * the person has no existing user link, is not marked deceased, and that the
 * approver is a Family Admin or above in that person's scope (or a confirming
 * kin). Every approval is written to audit_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_claims', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();

            $table->enum('status', ClaimStatus::values())->default(ClaimStatus::Pending->value);
            $table->text('evidence')->nullable();
            $table->text('relationship_statement')->nullable();
            $table->foreignId('supporting_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('verified_by_kin_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'person_id'], 'uq_profile_claim');
            $table->index(['person_id', 'status'], 'idx_claim_person_status');
            $table->index('status', 'idx_claim_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_claims');
    }
};

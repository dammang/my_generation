<?php

use App\Enums\ChangeRequestOperation;
use App\Enums\ChangeRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A proposed change. Verified genealogy is never silently overwritten.
 *
 * original_snapshot is what makes concurrent edits safe without locking: at
 * apply time the record's current state is compared against it, and a record
 * that moved since the proposal was filed is marked `superseded` with a
 * three-way diff surfaced to the reviewer.
 *
 * Append-only: never soft-deleted, because it is half the audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->enum('operation', ChangeRequestOperation::values());
            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id')->nullable();   // NULL for `create`

            // A bundle reviewed as one unit — "add my grandfather AND link him".
            // Partial application would leave an orphan person.
            $table->foreignId('parent_change_request_id')->nullable()
                ->constrained('change_requests')->nullOnDelete();

            $table->json('payload');
            $table->json('original_snapshot')->nullable();
            $table->json('diff')->nullable();

            $table->foreignId('scope_id')->nullable()->constrained('scopes')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();

            $table->enum('status', ChangeRequestStatus::values())
                ->default(ChangeRequestStatus::Pending->value);
            $table->timestamp('applied_at')->nullable();
            $table->json('applied_revision_ids')->nullable();

            $table->char('client_operation_id', 36)->nullable();   // offline idempotency

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'scope_id'], 'idx_cr_status_scope');
            $table->index(['target_type', 'target_id'], 'idx_cr_target');
            $table->index(['requested_by', 'status'], 'idx_cr_requester');
            $table->unique('client_operation_id', 'uq_cr_client_op');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};

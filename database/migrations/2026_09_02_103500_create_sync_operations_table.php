<?php

use App\Enums\SyncStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency ledger for offline writes.
 *
 * A replayed operation returns the stored response instead of executing again,
 * so a client that loses its acknowledgement and retries never creates a second
 * grandfather. Pruned after 30 days by the scheduler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('client_operation_id', 36);
            $table->string('endpoint', 120);
            $table->char('request_hash', 64);
            $table->enum('status', SyncStatus::values());
            $table->smallInteger('response_code');
            $table->json('response_body')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'client_operation_id'], 'uq_sync_client_op');
            $table->index('created_at', 'idx_sync_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};

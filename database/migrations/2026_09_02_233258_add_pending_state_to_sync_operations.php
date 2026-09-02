<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An in-flight state for the idempotency ledger.
 *
 * Without it the row is only written once the work is done, so two identical
 * requests racing each other both execute and the unique key catches the loser
 * too late — the second grandfather already exists. Claiming the key first
 * makes the index itself the lock.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE sync_operations
             MODIFY status ENUM('pending', 'applied', 'rejected', 'duplicate') NOT NULL"
        );

        // Nothing to record until the work finishes.
        DB::statement('ALTER TABLE sync_operations MODIFY response_code SMALLINT NULL');
    }

    public function down(): void
    {
        DB::statement("DELETE FROM sync_operations WHERE status = 'pending'");

        DB::statement(
            "ALTER TABLE sync_operations
             MODIFY status ENUM('applied', 'rejected', 'duplicate') NOT NULL"
        );

        DB::statement('ALTER TABLE sync_operations MODIFY response_code SMALLINT NOT NULL');
    }
};

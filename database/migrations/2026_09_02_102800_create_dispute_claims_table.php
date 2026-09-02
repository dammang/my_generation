<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $table->json('claimed_value');
            $table->text('rationale')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('supporter_count')->default(0);
            $table->timestamps();

            $table->index('dispute_id', 'idx_dc_dispute');
        });

        Schema::table('disputes', function (Blueprint $table) {
            $table->foreign('accepted_claim_id')
                ->references('id')->on('dispute_claims')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropForeign(['accepted_claim_id']);
        });
        Schema::dropIfExists('dispute_claims');
    }
};

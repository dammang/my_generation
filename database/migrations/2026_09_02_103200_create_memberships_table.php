<?php

use App\Enums\MembershipStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Belonging: "I am a member of the Zomi tribe, Guite clan." Capability is separate. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('scope_id')->constrained('scopes')->cascadeOnDelete();
            $table->enum('status', MembershipStatus::values())->default(MembershipStatus::Pending->value);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'scope_id'], 'uq_membership');
            $table->index(['scope_id', 'status'], 'idx_membership_scope_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};

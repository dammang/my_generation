<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capability: "I am an admin of the Guite clan."
 *
 * Global roles use Spatie's ordinary model_has_roles with no scope. Scoped
 * roles live here, and PermissionResolver merges both. Authority flows downward
 * by prefix-matching scopes.path, so a Tribe Admin needs no row per clan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scope_role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->foreignId('scope_id')->constrained('scopes')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->useCurrent();

            $table->primary(['user_id', 'role_id', 'scope_id'], 'pk_scope_role_user');
            $table->index(['scope_id', 'role_id'], 'idx_sru_scope_role');

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scope_role_user');
    }
};

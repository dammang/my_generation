<?php

use App\Enums\ClanRegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "I would like to start the Guite clan here."
 *
 * A request rather than a pending row in `clans`, because a clan that exists
 * but is not yet real leaks: it would appear in every listing that forgot to
 * exclude it, and the ones to worry about are the ones nobody thought about.
 * Nothing is created until somebody approves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_registrations', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->foreignId('tribe_id')->constrained('tribes')->cascadeOnDelete();

            // A clan inside a clan is how sub-clans are recorded, so a request
            // can name its parent.
            $table->foreignId('parent_clan_id')->nullable()->constrained('clans')->nullOnDelete();

            $table->string('name', 150);
            $table->string('native_name', 191)->nullable();
            $table->text('description')->nullable();

            // Where the clan begins. Optional at request time: somebody may
            // know the name of their clan long before they can point at the
            // person it descends from.
            $table->foreignId('ancestor_person_id')->nullable()->constrained('people')->nullOnDelete();

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('statement')->nullable();

            $table->enum('status', ClanRegistrationStatus::values())
                ->default(ClanRegistrationStatus::Pending->value);

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            // Set once approved, so the request records what it produced.
            $table->foreignId('clan_id')->nullable()->constrained('clans')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tribe_id', 'status'], 'idx_clanreg_tribe_status');
            $table->index(['requested_by', 'status'], 'idx_clanreg_requester');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_registrations');
    }
};

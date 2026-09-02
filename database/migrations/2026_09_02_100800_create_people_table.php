<?php

use App\Enums\Gender;
use App\Enums\PrivacyLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The person node. A user account is NOT a person: a deceased grandfather
 * exists here with no account, and a user is linked only by an approved
 * profile claim.
 *
 * Every name part is nullable — many ancestors in oral genealogy are recorded
 * with a single name, or with none at all beyond a relationship.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->string('first_name', 120)->nullable();
            $table->string('middle_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('native_name', 191)->nullable();
            $table->string('nickname', 120)->nullable();
            $table->string('display_name', 255);        // maintained by observer
            $table->string('sort_name', 255)->nullable(); // ASCII-folded, for ordering

            $table->enum('gender', Gender::values())->default(Gender::Unknown->value);

            $table->uncertainDate('birth');
            $table->foreignId('birth_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->uncertainDate('death');
            $table->foreignId('death_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->foreignId('burial_place_id')->nullable()->constrained('places')->nullOnDelete();

            // Convenience flag. The visibility resolver re-derives this from the
            // facts and treats a person with no dates as living — fail closed.
            $table->boolean('is_living')->default(true);
            $table->timestamp('living_reviewed_at')->nullable();

            $table->mediumText('biography')->nullable();
            $table->foreignId('profile_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();

            // Primary affiliation, used for scoping and indexing. Additional
            // tribes/clans live in person_affiliations.
            $table->foreignId('tribe_id')->nullable()->constrained('tribes')->nullOnDelete();
            $table->foreignId('clan_id')->nullable()->constrained('clans')->nullOnDelete();
            $table->foreignId('family_branch_id')->nullable()->constrained('family_branches')->nullOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('generations')->nullOnDelete();

            $table->privacyLevel('privacy_level', PrivacyLevel::Family->value);
            $table->verificationStatus();
            $table->boolean('has_open_dispute')->default(false);

            // Tombstone after a merge: the row is soft-deleted but kept so old
            // ULIDs and share links redirect to the winner instead of 404ing.
            $table->unsignedBigInteger('merged_into_person_id')->nullable();

            $table->string('external_ref', 64)->nullable();   // GEDCOM xref on import

            $table->contributable();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->index(['last_name', 'first_name'], 'idx_people_names');
            $table->index('sort_name', 'idx_people_sort');
            $table->index(['tribe_id', 'clan_id', 'family_branch_id'], 'idx_people_scope');
            $table->index(['family_branch_id', 'generation_id'], 'idx_people_branch_gen');
            $table->index(['is_living', 'privacy_level'], 'idx_people_living_privacy');
            $table->index('created_by', 'idx_people_created_by');
            $table->index('merged_into_person_id', 'idx_people_merged');
            $table->index('external_ref', 'idx_people_external');

            $table->foreign('merged_into_person_id')
                ->references('id')->on('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};

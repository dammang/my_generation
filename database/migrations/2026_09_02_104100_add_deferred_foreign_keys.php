<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circular references resolved.
 *
 * users ↔ people and people ↔ media are mutually dependent, so the columns are
 * created nullable in their own migrations and constrained here, once both
 * tables exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('person_id', 'fk_users_person')
                ->references('id')->on('people')->nullOnDelete();
            $table->foreign('avatar_media_id', 'fk_users_avatar')
                ->references('id')->on('media')->nullOnDelete();
        });

        Schema::table('family_branches', function (Blueprint $table) {
            $table->foreign('ancestor_person_id', 'fk_branch_ancestor')
                ->references('id')->on('people')->nullOnDelete();
        });

        Schema::table('person_names', function (Blueprint $table) {
            $table->foreign('source_id', 'fk_person_names_source')
                ->references('id')->on('sources')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('person_names', fn (Blueprint $t) => $t->dropForeign('fk_person_names_source'));
        Schema::table('family_branches', fn (Blueprint $t) => $t->dropForeign('fk_branch_ancestor'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('fk_users_person');
            $table->dropForeign('fk_users_avatar');
        });
    }
};

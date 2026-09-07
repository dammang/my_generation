<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a branch's own counting sits in the wider reckoning.
 *
 * A clan counts generations from the ancestor it descends from, but a family
 * inside it usually counts from its own founder — "1st generation of Jasuan",
 * who is himself the 11th from Pu Zo. Both numbers are true and people use
 * both, so the branch records where its number 1 falls on the older scale.
 *
 * Derived, not entered: computed from the clan ancestor's depths whenever the
 * branch's founder changes. Nullable because a clan need not name an ancestor
 * at all, and then there is no wider scale to sit on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_branches', function (Blueprint $table): void {
            $table->unsignedSmallInteger('generation_offset')->nullable()->after('ancestor_person_id');
        });
    }

    public function down(): void
    {
        Schema::table('family_branches', function (Blueprint $table): void {
            $table->dropColumn('generation_offset');
        });
    }
};

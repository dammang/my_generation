<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What somebody says about themselves when they ask to join a family.
 *
 * A request used to carry a user id and nothing else, so a reviewer was asked
 * to decide whether a stranger belongs to a clan knowing only their account
 * name. These are the questions a family actually asks at the door: who your
 * parents were, and your grandparents.
 *
 * Columns rather than a JSON blob: a reviewer reads them one at a time, and a
 * name somebody searches for has to be a column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->string('applicant_name', 191)->nullable()->after('status');
            $table->string('father_name', 191)->nullable()->after('applicant_name');
            $table->string('mother_name', 191)->nullable()->after('father_name');
            $table->string('grandfather_name', 191)->nullable()->after('mother_name');
            $table->string('grandmother_name', 191)->nullable()->after('grandfather_name');

            // Country only. A family archive has no business holding the
            // street address of somebody who has not been let in yet.
            $table->string('country', 2)->nullable()->after('grandmother_name');

            $table->string('contact', 191)->nullable()->after('country');

            // Where the selfie lives on the object store. Not a media record:
            // this is identification shown to a reviewer, not a family
            // photograph, and it must never surface in anybody's album.
            $table->string('photo_path', 255)->nullable()->after('contact');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropColumn([
                'applicant_name', 'father_name', 'mother_name',
                'grandfather_name', 'grandmother_name',
                'country', 'contact', 'photo_path',
            ]);
        });
    }
};

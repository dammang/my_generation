<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "They have died. Nobody knows when."
 *
 * The commonest fact in an oral archive, and until now unrecordable: is_living
 * is derived from the dates, so a person with no dates was always counted as
 * living — which is both wrong and a privacy decision, since living people are
 * masked from anybody outside the family.
 *
 * Declared rather than derived, and kept apart from the date columns because
 * it is a different kind of claim: the family says so, and no date was ever
 * written down. A date added later does not contradict it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->boolean('deceased_declared')->default(false)->after('is_living');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn('deceased_declared');
        });
    }
};

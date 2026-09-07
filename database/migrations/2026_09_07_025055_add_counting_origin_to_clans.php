<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a clan starts counting, as distinct from where it came from.
 *
 * These are two different people and both matter. A clan descends from an
 * ancestor generations back — the one everybody can name and nobody can
 * document — while its own numbering starts at whoever the family actually
 * counts from: "the first generation of Jasuan", who is himself the eleventh
 * from Pu Zo. Recording only the older one leaves every number too large to
 * mean anything; recording only the newer one loses the descent.
 *
 * generation_offset is derived — the origin's number on the older scale — and
 * recomputed whenever either person changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table): void {
            $table->foreignId('counting_origin_person_id')
                ->nullable()
                ->after('ancestor_person_id')
                ->constrained('people')
                ->nullOnDelete();

            $table->unsignedSmallInteger('generation_offset')->nullable()->after('counting_origin_person_id');
        });
    }

    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('counting_origin_person_id');
            $table->dropColumn('generation_offset');
        });
    }
};

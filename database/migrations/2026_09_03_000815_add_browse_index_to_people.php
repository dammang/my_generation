<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Browsing people within a tribe, in name order.
 *
 * Measured on 101,000 people: without this MySQL walks the sort_name index and
 * filters, so it reads until it happens to find 25 rows the viewer may see —
 * 107ms. With the filter columns ahead of the sort column it seeks straight to
 * the tribe and reads in order: 0.3ms.
 *
 * The cost is one more index on the largest table. Worth it: this is the query
 * behind the person list, which is the second thing anybody opens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->index(
                ['tribe_id', 'privacy_level', 'sort_name', 'id'],
                'idx_people_browse',
            );
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex('idx_people_browse');
        });
    }
};

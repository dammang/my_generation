<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarchical gazetteer: country → state → district → township → village.
 * Depth is data, not schema — jurisdictions differ, so `type` is a string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->foreignId('parent_id')->nullable()->constrained('places')->nullOnDelete();
            $table->string('path', 500)->default('');   // materialised id path, e.g. /1/14/57/
            $table->unsignedTinyInteger('depth')->default(0);

            $table->string('name', 150);
            $table->string('native_name', 191)->nullable();
            $table->string('type', 40)->default('other');
            $table->char('country_code', 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // [{name, from_year, to_year}] — places get renamed, and old records
            // use the old name. Needed for both search and historical accuracy.
            $table->json('historical_names')->nullable();

            $table->unsignedInteger('people_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->index('parent_id', 'idx_places_parent');
            $table->index(['country_code', 'type'], 'idx_places_country_type');
            $table->index(['latitude', 'longitude'], 'idx_places_latlng');
            $table->index('name', 'idx_places_name');
        });

        // Prefix index: only the head of the path is ever matched (LIKE '/1/%').
        DB::statement('CREATE INDEX idx_places_path ON places (path(191))');
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};

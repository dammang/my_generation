<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Incremented on write, reconciled nightly against revisions. Powers "My Contributions". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_stats', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('people_added')->default(0);
            $table->unsignedInteger('relationships_added')->default(0);
            $table->unsignedInteger('unions_added')->default(0);
            $table->unsignedInteger('events_added')->default(0);
            $table->unsignedInteger('stories_added')->default(0);
            $table->unsignedInteger('sources_added')->default(0);
            $table->unsignedInteger('media_added')->default(0);
            $table->unsignedInteger('changes_approved')->default(0);
            $table->unsignedInteger('changes_rejected')->default(0);
            $table->unsignedInteger('verifications_made')->default(0);
            $table->timestamp('last_contributed_at')->nullable();
            $table->timestamp('recalculated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_stats');
    }
};

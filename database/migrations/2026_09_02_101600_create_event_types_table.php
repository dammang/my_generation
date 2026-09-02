<?php

use App\Enums\EventCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lookup table, not an enum, because tribes have culturally specific events —
 * naming ceremonies, feasts of merit, clan installations — and adding one must
 * never require a migration. tribe_id NULL means system-wide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('label', 100);
            $table->enum('category', EventCategory::values())->default(EventCategory::Other->value);
            $table->foreignId('tribe_id')->nullable()->constrained('tribes')->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->string('icon', 40)->nullable();
            $table->smallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['tribe_id', 'category'], 'idx_event_types_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};

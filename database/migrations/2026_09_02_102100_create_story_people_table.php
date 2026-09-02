<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_people', function (Blueprint $table) {
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->enum('role', ['subject', 'mentioned', 'narrator'])->default('mentioned');
            $table->timestamps();

            $table->primary(['story_id', 'person_id'], 'pk_story_people');
            $table->index('person_id', 'idx_sp_person');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_people');
    }
};

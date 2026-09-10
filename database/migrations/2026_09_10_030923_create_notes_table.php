<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Something one member wants said about a person, to a chosen audience.
 *
 * Records go quiet: somebody hides their name and there is then no way to say
 * who they were to the people entitled to know. A note is that — written by
 * any member, on any record, read only by whoever the writer chose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            /**
             * The author's own record at the time of writing, kept here rather
             * than read from the account later: "my descendants" must go on
             * meaning the same people after the author relinks their profile
             * or somebody edits the account.
             */
            $table->foreignId('author_person_id')->nullable()
                ->constrained('people')->nullOnDelete();

            $table->text('body');
            $table->string('audience', 20)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['person_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};

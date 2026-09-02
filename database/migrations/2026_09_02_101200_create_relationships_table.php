<?php

use App\Enums\Certainty;
use App\Enums\DatePrecision;
use App\Enums\RelationshipSubtype;
use App\Enums\RelationshipType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Directed, canonical, non-partner edges.
 *
 * Direction is fixed and the inverse is NEVER stored: for parent_child,
 * person_id is always the parent. "Who are X's parents" reads idx_rel_reverse;
 * "who are X's children" reads idx_rel_forward. Storing both directions would
 * double writes and guarantee the drift bug where one row is edited and its
 * mirror is not.
 *
 * Spouses are absent by design — see the unions table.
 * Siblings are derived from shared parents; sibling_asserted exists only for
 * the genuine oral-genealogy case "these two are brothers, parents unknown".
 *
 * Competing claims coexist deliberately: two contributors asserting different
 * fathers for one child produce two rows, both flagged disputed. The unique key
 * does not prevent that — resolving it is a human decision recorded in
 * `disputes`, not a database error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationships', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();

            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();          // parent / guardian
            $table->foreignId('related_person_id')->constrained('people')->restrictOnDelete();  // child / ward

            $table->enum('relationship_type', RelationshipType::values())
                ->default(RelationshipType::ParentChild->value);
            $table->enum('relationship_subtype', RelationshipSubtype::values())
                ->default(RelationshipSubtype::Unknown->value);
            $table->string('custom_label', 80)->nullable();

            $table->boolean('is_biological')->nullable();   // tri-state: yes / no / unknown
            $table->foreignId('union_id')->nullable()->constrained('unions')->nullOnDelete();

            // A single precision covers both ends of the span (adoption,
            // guardianship, fostering), rather than the full four-column
            // pattern twice — which would produce an `end_date_end` column.
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('date_precision', DatePrecision::values())
                ->default(DatePrecision::Unknown->value);
            $table->string('date_text', 120)->nullable();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();

            $table->enum('certainty', Certainty::values())->default(Certainty::Possible->value);
            $table->verificationStatus();
            $table->text('notes')->nullable();

            $table->contributable();
            $table->softDeletesWithToken();
            $table->timestamps();

            $table->unique(
                ['person_id', 'related_person_id', 'relationship_type', 'relationship_subtype', 'deleted_token'],
                'uq_rel'
            );
            $table->index(['person_id', 'relationship_type', 'related_person_id'], 'idx_rel_forward');
            $table->index(['related_person_id', 'relationship_type', 'person_id'], 'idx_rel_reverse');
            $table->index('union_id', 'idx_rel_union');
        });

        DB::statement(
            'ALTER TABLE relationships ADD CONSTRAINT chk_rel_not_self
             CHECK (person_id <> related_person_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};

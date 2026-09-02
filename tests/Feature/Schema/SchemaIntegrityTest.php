<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use App\Models\Person;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The invariants the database itself must enforce, not merely the application.
 * These are the rules that would corrupt every downstream traversal if code
 * elsewhere ever got them wrong.
 */
class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_person_cannot_be_their_own_parent(): void
    {
        $person = Person::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('relationships')->insert([
            'ulid' => '01TESTTESTTESTTESTTESTTEST',
            'person_id' => $person->id,
            'related_person_id' => $person->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_union_pair_must_be_stored_in_canonical_order(): void
    {
        // Normalising to partner_1_id < partner_2_id is what makes the unique
        // key actually prevent the same marriage being entered from either side.
        [$a, $b] = Person::factory()->count(2)->create()->all();

        $this->expectException(QueryException::class);

        DB::table('unions')->insert([
            'ulid' => '01TESTUNIONTESTUNIONTESTUN',
            'partner_1_id' => max($a->id, $b->id),
            'partner_2_id' => min($a->id, $b->id),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_soft_deleting_frees_a_unique_value_for_reuse(): void
    {
        // The classic soft-delete uniqueness bug: MySQL treats NULLs as
        // distinct, so a unique key on (email, deleted_at) would NOT stop two
        // live rows. deleted_token is what closes that hole.
        $first = User::factory()->create(['email' => 'kin.tun@example.test']);
        $first->delete();

        $second = User::factory()->create(['email' => 'kin.tun@example.test']);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($first->id, (int) $first->fresh()->deleted_token);
        $this->assertSame(0, (int) $second->deleted_token);
    }

    public function test_two_live_rows_cannot_share_a_unique_value(): void
    {
        User::factory()->create(['email' => 'duplicate@example.test']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'duplicate@example.test']);
    }

    public function test_restoring_reclaims_the_live_token(): void
    {
        $user = User::factory()->create();
        $user->delete();
        $user->restore();

        $this->assertSame(0, (int) $user->fresh()->deleted_token);
    }

    public function test_every_domain_table_exists(): void
    {
        $expected = [
            'users', 'scopes', 'memberships', 'scope_role_user', 'profile_claims', 'device_tokens',
            'tribes', 'clans', 'family_branches', 'generations', 'places',
            'people', 'person_names', 'person_affiliations', 'relationships', 'unions',
            'union_children', 'family_edges', 'lineage_depths',
            'event_types', 'person_events', 'stories', 'story_people',
            'oral_histories', 'oral_history_segments', 'media',
            'sources', 'citations',
            'change_requests', 'change_request_reviews', 'revisions', 'disputes', 'dispute_claims',
            'person_match_keys', 'duplicate_candidates', 'person_merges', 'contribution_stats',
            'sync_operations', 'share_links', 'saved_people', 'audit_logs',
        ];

        foreach ($expected as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Missing table: {$table}"
            );
        }
    }

    public function test_the_traversal_table_has_both_covering_indexes(): void
    {
        // Tree latency depends entirely on these two staying covering.
        $indexes = collect(DB::select('SHOW INDEX FROM family_edges'))
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();

        $this->assertContains('PRIMARY', $indexes);
        $this->assertContains('idx_fe_child', $indexes);
    }
}

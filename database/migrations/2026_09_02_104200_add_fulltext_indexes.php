<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full-text search indexes.
 *
 * Two parsers, deliberately. The default parser splits on whitespace and gives
 * better relevance for space-delimited prose. The ngram parser tokenises by
 * character n-grams, which is what makes Burmese and other scripts without word
 * delimiters searchable at all — so it goes on the native-script columns.
 *
 * Written as raw SQL because Laravel's fullText() cannot express WITH PARSER.
 * Skipped on non-MySQL connections so the test suite can run on SQLite.
 *
 * MariaDB has no ngram parser and reports the same `mysql` driver name, so the
 * driver check alone let it through and it failed on the ALTER. There the
 * native-script columns get an ordinary full-text index instead: the schema
 * still applies, but those columns tokenise on whitespace, which is close to
 * useless for a script that does not use it. Nothing queries these indexes
 * yet — when search is built, that is the point at which MariaDB has to be
 * answered properly rather than degraded past.
 */
return new class extends Migration
{
    /** @var array<int, array{0:string,1:string,2:string,3:bool}> table, index, columns, ngram */
    private array $indexes = [
        ['people',       'ft_people_latin',  'display_name, nickname, first_name, last_name', false],
        ['people',       'ft_people_native', 'native_name',                                    true],
        ['person_names', 'ft_person_names',  'name',                                           true],
        ['places',       'ft_places_latin',  'name',                                           false],
        ['places',       'ft_places_native', 'native_name',                                    true],
        ['stories',      'ft_stories',       'title, summary, body',                           false],
        ['sources',      'ft_sources',       'title, description',                             false],
    ];

    public function up(): void
    {
        if (! $this->isMySql()) {
            return;
        }

        $ngramAvailable = $this->supportsNgramParser();

        foreach ($this->indexes as [$table, $index, $columns, $ngram]) {
            // A failure part-way through this migration leaves earlier indexes
            // in place while the migration itself is unrecorded, so re-running
            // would collide with its own previous attempt.
            if ($this->indexExists($table, $index)) {
                continue;
            }

            $parser = $ngram && $ngramAvailable ? ' WITH PARSER ngram' : '';
            DB::statement("ALTER TABLE `{$table}` ADD FULLTEXT INDEX `{$index}` ({$columns}){$parser}");
        }
    }

    public function down(): void
    {
        if (! $this->isMySql()) {
            return;
        }

        foreach (array_reverse($this->indexes) as [$table, $index]) {
            if ($this->indexExists($table, $index)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
            }
        }
    }

    private function isMySql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }

    /**
     * MariaDB answers to the `mysql` driver but has no ngram parser at all.
     */
    private function supportsNgramParser(): bool
    {
        $version = (string) (DB::selectOne('select version() as v')->v ?? '');

        return ! str_contains(strtolower($version), 'mariadb');
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::selectOne(
            'select 1 as found from information_schema.statistics
             where table_schema = database() and table_name = ? and index_name = ? limit 1',
            [$table, $index],
        ) !== null;
    }
};

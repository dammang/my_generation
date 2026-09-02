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

        foreach ($this->indexes as [$table, $index, $columns, $ngram]) {
            $parser = $ngram ? ' WITH PARSER ngram' : '';
            DB::statement("ALTER TABLE `{$table}` ADD FULLTEXT INDEX `{$index}` ({$columns}){$parser}");
        }
    }

    public function down(): void
    {
        if (! $this->isMySql()) {
            return;
        }

        foreach (array_reverse($this->indexes) as [$table, $index]) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }

    private function isMySql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }
};

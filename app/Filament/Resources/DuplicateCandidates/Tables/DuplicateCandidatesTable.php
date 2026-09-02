<?php

declare(strict_types=1);

namespace App\Filament\Resources\DuplicateCandidates\Tables;

use App\Actions\Merge\MergePeople;
use App\Enums\DuplicateStatus;
use App\Models\DuplicateCandidate;
use App\Models\Person;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Possible duplicates, and the merge decision.
 *
 * The score is shown with its reasoning, because "0.91" is not evidence — the
 * reviewer needs to see that the names agree phonetically, the birth years are
 * one apart and both were born in the same village. Nothing merges
 * automatically at any score.
 */
class DuplicateCandidatesTable
{
    /** Fields a reviewer chooses between when the two records disagree. */
    private const COMPARABLE = [
        'display_name' => 'Name',
        'birth_date_text' => 'Birth',
        'death_date_text' => 'Death',
        'birth_place_id' => 'Birth place',
        'biography' => 'Biography',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('score', 'desc')
            ->columns([
                TextColumn::make('personA.display_name')->label('Record A')->searchable(),
                TextColumn::make('personB.display_name')->label('Record B')->searchable(),

                TextColumn::make('score')
                    ->badge()
                    ->color(fn (float $state) => $state >= 0.95 ? 'danger' : ($state >= 0.88 ? 'warning' : 'gray'))
                    ->formatStateUsing(fn (float $state) => number_format($state * 100, 0).'%')
                    ->sortable(),

                // See the note in ChangeRequestsTable: array-cast columns are
                // formatted element by element, so the state is derived here.
                TextColumn::make('signals')
                    ->label('Why')
                    ->html()
                    ->state(fn (DuplicateCandidate $record): string => self::renderSignals($record->signals))
                    ->wrap(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (DuplicateStatus $state) => match ($state) {
                        DuplicateStatus::Open => 'warning',
                        DuplicateStatus::Merged => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(DuplicateStatus::class)
                    ->default(DuplicateStatus::Open->value),
            ])
            ->recordActions([
                self::mergeAction(),
                self::keepSeparateAction(),
            ]);
    }

    private static function mergeAction(): Action
    {
        return Action::make('merge')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('primary')
            ->visible(fn (DuplicateCandidate $record) => $record->status === DuplicateStatus::Open)
            ->modalHeading('Merge these records')
            ->modalDescription('The record you keep absorbs the other. The other is kept as a tombstone so existing links still resolve, and the merge can be reversed.')
            ->modalSubmitActionLabel('Merge')
            ->schema(fn (DuplicateCandidate $record) => self::comparisonForm($record))
            ->action(function (DuplicateCandidate $record, array $data): void {
                $record->loadMissing(['personA', 'personB']);

                $keepA = ($data['keep'] ?? 'a') === 'a';
                $winner = $keepA ? $record->personA : $record->personB;
                $loser = $keepA ? $record->personB : $record->personA;

                // Field choices are expressed relative to the winner, so the
                // action does not need to know which record the reviewer kept.
                $choices = [];

                foreach (array_keys(self::COMPARABLE) as $field) {
                    $chosen = $data["field_{$field}"] ?? null;

                    if ($chosen !== null && $chosen !== ($keepA ? 'a' : 'b')) {
                        $choices[$field] = 'loser';
                    }
                }

                $merge = app(MergePeople::class)->handle(auth()->user(), $winner, $loser, $choices);

                Notification::make()
                    ->success()
                    ->title('Records merged')
                    ->body("{$loser->display_name} was merged into {$winner->display_name}. Reference {$merge->ulid}.")
                    ->send();
            });
    }

    private static function keepSeparateAction(): Action
    {
        return Action::make('keepSeparate')
            ->label('Keep separate')
            ->icon('heroicon-o-scissors')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('These will not be raised as a duplicate again.')
            ->visible(fn (DuplicateCandidate $record) => $record->status === DuplicateStatus::Open)
            ->action(function (DuplicateCandidate $record): void {
                $record->update([
                    'status' => DuplicateStatus::KeptSeparate,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                Notification::make()->success()->title('Kept separate')->send();
            });
    }

    /** @return array<int, Component> */
    private static function comparisonForm(DuplicateCandidate $record): array
    {
        $record->loadMissing(['personA', 'personB']);

        $fields = [
            Radio::make('keep')
                ->label('Which record should survive?')
                ->options([
                    'a' => self::describe($record->personA),
                    'b' => self::describe($record->personB),
                ])
                ->default('a')
                ->required(),
        ];

        // Only offer a choice where the two records actually disagree; a form
        // full of identical pairs hides the decisions that matter.
        foreach (self::COMPARABLE as $field => $label) {
            $a = $record->personA?->getAttribute($field);
            $b = $record->personB?->getAttribute($field);

            if (blank($a) || blank($b) || $a === $b) {
                continue;
            }

            $fields[] = Radio::make("field_{$field}")
                ->label($label)
                ->options(['a' => (string) $a, 'b' => (string) $b])
                ->default('a')
                ->inline();
        }

        return $fields;
    }

    private static function describe(?Person $person): string
    {
        if ($person === null) {
            return 'Unknown';
        }

        return trim(sprintf(
            '%s %s',
            $person->display_name,
            $person->lifespan() === null ? '' : '('.$person->lifespan().')',
        ));
    }

    /** @param  array<string, mixed>|null  $signals */
    private static function renderSignals(?array $signals): string
    {
        if (blank($signals)) {
            return '<span class="text-gray-400">—</span>';
        }

        $parts = [];

        foreach ($signals as $key => $value) {
            // Null means the feature could not be judged — the records simply
            // do not both carry that fact. Saying so is more honest than
            // showing it as a failed match.
            if ($value === null || $value === false) {
                continue;
            }

            $parts[] = match ($key) {
                'name_similarity' => 'names '.number_format((float) $value * 100, 0).'% alike',
                'name_phonetic' => 'same phonetic name',
                'birth_year' => 'birth years agree',
                'death_year' => 'death years agree',
                'birth_place' => 'same birthplace',
                'shared_parent' => 'shared parent',
                'shared_spouse' => 'shared spouse',
                'contradictory_dates' => '<span class="text-danger-600">dates contradict</span>',
                default => null,
            };
        }

        return implode(' · ', array_filter($parts)) ?: '<span class="text-gray-400">weak match</span>';
    }
}

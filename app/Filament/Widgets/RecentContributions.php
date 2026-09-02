<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Revision;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The last few genealogical changes, from the revision ledger.
 *
 * This is the "who touched what" view an archive needs: attribution is part of
 * the evidence, and a change nobody can trace is a change nobody can weigh.
 */
class RecentContributions extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent changes')
            ->description('From the revision ledger — every change to an audited field, with its author.')
            ->query(
                Revision::query()
                    ->with('changedBy:id,name')
                    ->latest('created_at')
                    ->limit(15)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label('When')->since(),

                TextColumn::make('revisionable_type')
                    ->label('Record')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str($state)->headline()),

                TextColumn::make('action')->badge(),

                TextColumn::make('field')
                    ->label('Field')
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : str($state)->headline()),

                TextColumn::make('old_value')
                    ->label('From')
                    ->formatStateUsing(fn ($state) => self::readable($state))
                    ->limit(40),

                TextColumn::make('new_value')
                    ->label('To')
                    ->formatStateUsing(fn ($state) => self::readable($state))
                    ->limit(40),

                TextColumn::make('changedBy.name')->label('By')->placeholder('System'),

                TextColumn::make('reason')->label('Reason')->limit(50)->placeholder('—'),
            ]);
    }

    private static function readable(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_array($value) => count($value).' fields',
            is_bool($value) => $value ? 'yes' : 'no',
            default => (string) $value,
        };
    }
}

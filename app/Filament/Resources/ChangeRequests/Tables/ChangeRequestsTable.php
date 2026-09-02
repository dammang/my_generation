<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChangeRequests\Tables;

use App\Actions\Verification\ApplyChangeRequest;
use App\Enums\ChangeRequestStatus;
use App\Exceptions\ChangeRequestSupersededException;
use App\Models\ChangeRequest;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The verification queue.
 *
 * The diff is the point of this screen: a reviewer decides on evidence, not on
 * a record id, so what changed is shown inline rather than behind a click.
 */
class ChangeRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),

                TextColumn::make('target_type')
                    ->label('Record')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => str($state ?? '')->headline()),

                TextColumn::make('operation')->badge(),

                // Derived from the record, not from the array-cast column:
                // Filament treats array state as a list of values and formats
                // each element separately, which a whole-array formatter
                // cannot receive.
                TextColumn::make('diff')
                    ->label('Proposed change')
                    ->html()
                    ->state(fn (ChangeRequest $record): string => self::renderDiff($record->diff))
                    ->wrap(),

                TextColumn::make('reason')
                    ->label('Evidence')
                    ->wrap()
                    ->limit(80)
                    ->placeholder('No reason given'),

                TextColumn::make('requester.name')->label('Submitted by')->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ChangeRequestStatus $state) => match ($state) {
                        ChangeRequestStatus::Pending => 'warning',
                        ChangeRequestStatus::Approved => 'success',
                        ChangeRequestStatus::Rejected => 'danger',
                        ChangeRequestStatus::Superseded => 'gray',
                        default => 'secondary',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ChangeRequestStatus::class)
                    ->default(ChangeRequestStatus::Pending->value),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::approveAction(),
                    self::rejectAction(),
                ]),
            ]);
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ChangeRequest $record) => $record->status === ChangeRequestStatus::Pending)
            ->schema([
                Textarea::make('comment')->label('Note (optional)')->rows(2),
            ])
            ->action(function (ChangeRequest $record, array $data): void {
                try {
                    app(ApplyChangeRequest::class)->handle($record, auth()->user(), $data['comment'] ?? null);

                    Notification::make()
                        ->success()
                        ->title('Change applied')
                        ->body('The record was updated and a revision recorded.')
                        ->send();
                } catch (ChangeRequestSupersededException $e) {
                    // The record moved while the proposal sat in the queue.
                    // Showing the conflict is the whole point of detecting it.
                    Notification::make()
                        ->warning()
                        ->title('Record changed since this was submitted')
                        ->body('Conflicting fields: '.implode(', ', array_keys($e->conflicts))
                            .'. The proposal has been marked superseded.')
                        ->persistent()
                        ->send();
                } catch (AuthorizationException $e) {
                    Notification::make()->danger()->title('Not permitted')->body($e->getMessage())->send();
                }
            });
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (ChangeRequest $record) => $record->status === ChangeRequestStatus::Pending)
            ->schema([
                Textarea::make('comment')
                    ->label('Why is this being rejected?')
                    ->helperText('The contributor sees this. Say what evidence would change the answer.')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (ChangeRequest $record, array $data): void {
                try {
                    app(ApplyChangeRequest::class)->reject($record, auth()->user(), $data['comment']);

                    Notification::make()->success()->title('Rejected')->send();
                } catch (AuthorizationException $e) {
                    Notification::make()->danger()->title('Not permitted')->body($e->getMessage())->send();
                }
            });
    }

    /** @param  array<string, array{0: mixed, 1: mixed}>|null  $diff */
    private static function renderDiff(?array $diff): string
    {
        if (blank($diff)) {
            return '<span class="text-gray-400">No field changes</span>';
        }

        $rows = [];

        foreach ($diff as $field => [$old, $new]) {
            $rows[] = sprintf(
                '<div><span class="font-medium">%s</span>: <span class="line-through opacity-60">%s</span> → <span class="font-semibold">%s</span></div>',
                e(str($field)->headline()),
                e($old === null ? '—' : (string) $old),
                e($new === null ? '—' : (string) $new),
            );
        }

        return implode('', $rows);
    }
}

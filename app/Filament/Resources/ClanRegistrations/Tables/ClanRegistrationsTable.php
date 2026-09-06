<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanRegistrations\Tables;

use App\Actions\Clans\DecideClanRegistration;
use App\Enums\ClanRegistrationStatus;
use App\Models\ClanRegistration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

class ClanRegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Asked')
                    ->since()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Clan')
                    ->description(fn (ClanRegistration $record): ?string => $record->native_name)
                    ->searchable(),

                TextColumn::make('tribe.name')
                    ->label('In tribe')
                    ->description(fn (ClanRegistration $record): ?string => $record->parentClan?->name === null
                        ? null
                        : 'under '.$record->parentClan->name),

                TextColumn::make('requester.name')
                    ->label('Asked by')
                    ->description(fn (ClanRegistration $record): ?string => $record->requester?->email)
                    ->searchable(),

                TextColumn::make('ancestor.display_name')
                    ->label('Starts from')
                    ->placeholder('Not named'),

                // What the requester says. It is the case being made, so it
                // belongs in the queue rather than a click away.
                TextColumn::make('statement')
                    ->label('Their reason')
                    ->wrap()
                    ->limit(140)
                    ->placeholder('None given'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ClanRegistrationStatus $state) => match ($state) {
                        ClanRegistrationStatus::Pending => 'warning',
                        ClanRegistrationStatus::Approved => 'success',
                        ClanRegistrationStatus::Rejected => 'danger',
                        ClanRegistrationStatus::Withdrawn => 'gray',
                    }),

                TextColumn::make('clan.name')
                    ->label('Became')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ClanRegistrationStatus::class)
                    ->default(ClanRegistrationStatus::Pending->value),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::decisionAction('approve', 'heroicon-o-check-circle', 'success'),
                    self::decisionAction('reject', 'heroicon-o-x-circle', 'danger'),
                ]),
            ]);
    }

    /**
     * Approving creates the clan and makes the requester its administrator, so
     * the confirmation says so rather than asking "are you sure?" about
     * something whose consequences are not on screen.
     */
    private static function decisionAction(string $name, string $icon, string $colour): Action
    {
        return Action::make($name)
            ->icon($icon)
            ->color($colour)
            ->requiresConfirmation()
            ->modalDescription(fn (ClanRegistration $record): string => $name === 'approve'
                ? "This creates the {$record->name} clan and makes {$record->requester?->name} its administrator."
                : "This refuses the request. {$record->requester?->name} can ask again.")
            ->schema([
                Textarea::make('note')
                    ->label('Note (optional)')
                    ->helperText('Recorded with the decision.')
                    ->rows(2),
            ])
            ->visible(fn (ClanRegistration $record) => $record->status === ClanRegistrationStatus::Pending)
            ->action(fn (ClanRegistration $record, array $data) => self::decide($record, $name, $data['note'] ?? null));
    }

    /**
     * The action is the authority, not this table.
     *
     * DecideClanRegistration already refuses a request decided by its own
     * author, a tribe the decider does not run, and one already decided.
     * Copying those rules here would give two places to disagree.
     */
    private static function decide(ClanRegistration $record, string $decision, ?string $note): void
    {
        $admin = auth()->user();

        if ($admin === null) {
            return;
        }

        try {
            $action = app(DecideClanRegistration::class);

            $decision === 'approve'
                ? $action->approve($record, $admin, $note)
                : $action->reject($record, $admin, $note);
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title('Not permitted')->body($e->getMessage())->send();

            return;
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Could not decide this request')->body($e->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($decision === 'approve' ? 'Clan created' : 'Rejected')
            ->body($decision === 'approve'
                ? "{$record->name} exists, and {$record->requester?->name} administers it."
                : null)
            ->send();
    }
}

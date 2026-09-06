<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProfileClaims\Tables;

use App\Actions\Claims\DecideProfileClaim;
use App\Enums\ClaimStatus;
use App\Models\ProfileClaim;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

class ProfileClaimsTable
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

                TextColumn::make('user.name')
                    ->label('Account')
                    ->description(fn (ProfileClaim $record): ?string => $record->user?->email)
                    ->searchable(),

                TextColumn::make('person.display_name')
                    ->label('Claims to be')
                    ->searchable(),

                // The claimant's own words are the evidence being weighed, so
                // they belong in the queue rather than a click away.
                TextColumn::make('relationship_statement')
                    ->label('Their statement')
                    ->wrap()
                    ->limit(140)
                    ->placeholder('None given'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ClaimStatus $state) => match ($state) {
                        ClaimStatus::Pending => 'warning',
                        ClaimStatus::Approved => 'success',
                        ClaimStatus::Rejected => 'danger',
                        ClaimStatus::Withdrawn => 'gray',
                    }),

                TextColumn::make('decidedBy.name')
                    ->label('Decided by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ClaimStatus::class)
                    ->default(ClaimStatus::Pending->value),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::decisionAction('approve', 'heroicon-o-check-circle', 'success'),
                    self::decisionAction('reject', 'heroicon-o-x-circle', 'danger'),
                ]),
            ]);
    }

    /**
     * Approving is not a formality: it makes this account close kin of
     * everyone around that person, so the note explaining the decision is
     * asked for and recorded.
     */
    private static function decisionAction(string $name, string $icon, string $colour): Action
    {
        return Action::make($name)
            ->icon($icon)
            ->color($colour)
            ->requiresConfirmation()
            ->schema([
                Textarea::make('note')
                    ->label('Note (optional)')
                    ->helperText('Recorded with the decision.')
                    ->rows(2),
            ])
            ->visible(fn (ProfileClaim $record) => $record->status === ClaimStatus::Pending)
            ->action(fn (ProfileClaim $record, array $data) => self::decide($record, $name, $data['note'] ?? null));
    }

    /**
     * The action is the authority, not this table.
     *
     * DecideProfileClaim already refuses a claim decided by its own author,
     * refuses a scope the decider does not administer, and refuses a person
     * somebody else was verified as while the claim sat in the queue. Copying
     * those rules here would give two places to disagree; calling it and
     * reporting what it says gives one.
     */
    private static function decide(ProfileClaim $record, string $decision, ?string $note): void
    {
        $admin = auth()->user();

        if ($admin === null) {
            return;
        }

        try {
            $action = app(DecideProfileClaim::class);

            $decision === 'approve'
                ? $action->approve($record, $admin, $note)
                : $action->reject($record, $admin, $note);
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title('Not permitted')->body($e->getMessage())->send();

            return;
        } catch (Throwable $e) {
            // A rule refusal — already decided, or somebody else was verified
            // as this person in the meantime. Both are worth reading rather
            // than being shown as a generic failure.
            Notification::make()->danger()->title('Could not decide this claim')->body($e->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($decision === 'approve' ? 'Approved' : 'Rejected')
            ->body($decision === 'approve'
                ? $record->user?->name.' is now recorded as '.$record->person?->display_name.'.'
                : null)
            ->send();
    }
}

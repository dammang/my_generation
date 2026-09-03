<?php

declare(strict_types=1);

namespace App\Filament\Resources\Memberships\Tables;

use App\Actions\Access\DecideMembership;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Notifications\MembershipDecided;
use App\Services\Permissions\PermissionResolver;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * "I belong to this tribe" — the queue nobody could act on until this
 * existed. The API has approved/rejected this since Membership shipped;
 * nothing in the product ever gave an administrator a way to reach it,
 * mobile or here, which meant every join request sat pending forever.
 */
class MembershipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->since()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Applicant')
                    ->description(fn (Membership $record): ?string => $record->user?->email)
                    ->searchable(),

                TextColumn::make('scope.scopeable.name')
                    ->label('Tribe / clan / branch'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (MembershipStatus $state) => match ($state) {
                        MembershipStatus::Pending => 'warning',
                        MembershipStatus::Active => 'success',
                        MembershipStatus::Rejected => 'danger',
                        MembershipStatus::Left => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MembershipStatus::class)
                    ->default(MembershipStatus::Pending->value),
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
            ->requiresConfirmation()
            ->visible(fn (Membership $record) => $record->status === MembershipStatus::Pending
                && self::currentUserAdministers($record))
            ->action(fn (Membership $record) => self::decide($record, MembershipStatus::Active));
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Membership $record) => $record->status === MembershipStatus::Pending
                && self::currentUserAdministers($record))
            ->action(fn (Membership $record) => self::decide($record, MembershipStatus::Rejected));
    }

    /**
     * Being let into the panel at all does not mean administering every
     * scope in it — a clan admin should not see an approve button for a
     * tribe they hold no role in. Same check the API makes before granting
     * the endpoint at all.
     */
    private static function currentUserAdministers(Membership $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->is_super_admin) {
            return true;
        }

        $record->loadMissing('scope');

        return $record->scope !== null
            && app(PermissionResolver::class)->administersMembership($user, $record->scope->path);
    }

    private static function decide(Membership $record, MembershipStatus $decision): void
    {
        $admin = auth()->user();

        if (! self::currentUserAdministers($record)) {
            // The action is hidden for this case already; this is the guard
            // against acting on a stale, already-rendered page.
            Notification::make()
                ->danger()
                ->title('Not permitted')
                ->body('You do not administer this scope.')
                ->send();

            throw new AuthorizationException('You do not administer this scope.');
        }

        app(DecideMembership::class)->handle($record, $decision, $admin);
        $record->user?->notify(new MembershipDecided($record));

        Notification::make()
            ->success()
            ->title($decision === MembershipStatus::Active ? 'Approved' : 'Rejected')
            ->send();
    }
}

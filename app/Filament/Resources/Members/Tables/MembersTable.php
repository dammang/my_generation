<?php

declare(strict_types=1);

namespace App\Filament\Resources\Members\Tables;

use App\Models\Clan;
use App\Models\Membership;
use App\Services\Media\MediaUrlResolver;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('approved_at', 'desc')
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Photo')
                    ->circular()
                    ->getStateUsing(fn (Membership $record): ?string => self::photo($record)),

                TextColumn::make('applicant_name')
                    ->label('Name')
                    // What they wrote, falling back to the account they signed
                    // up with — a tribe membership carries no answers at all.
                    ->getStateUsing(fn (Membership $r): string => $r->applicant_name ?? $r->user?->name ?? '—')
                    ->description(fn (Membership $r): ?string => $r->user?->email)
                    ->searchable(['applicant_name'])
                    ->sortable(),

                TextColumn::make('scope.scopeable.name')
                    ->label('Belongs to')
                    ->badge(),

                TextColumn::make('father_name')
                    ->label('Parents')
                    ->getStateUsing(fn (Membership $r): string => collect([
                        $r->father_name,
                        $r->mother_name,
                    ])->filter()->implode(' · ') ?: '—')
                    ->searchable(['father_name', 'mother_name'])
                    ->wrap(),

                TextColumn::make('contact')
                    ->label('Contact')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('country')
                    ->label('Country')
                    ->badge(),

                TextColumn::make('approved_at')
                    ->label('Joined')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                // A clan is what a committee administers, and there will be
                // more than one.
                SelectFilter::make('clan')
                    ->label('Clan')
                    ->options(fn (): array => Clan::orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $clanId) => $q->whereHas(
                            'scope',
                            fn (Builder $s) => $s
                                ->where('scopeable_type', 'clan')
                                ->where('scopeable_id', $clanId),
                        ),
                    )),
            ]);
    }

    /**
     * Signed and short-lived, like any other private object. Null where the
     * disk cannot sign, which shows as no photograph rather than a broken one.
     */
    private static function photo(Membership $membership): ?string
    {
        if (blank($membership->photo_path)) {
            return null;
        }

        $disk = config('filesystems.disks.r2') !== null ? 'r2' : 'local';

        try {
            return Storage::disk($disk)->temporaryUrl(
                $membership->photo_path,
                now()->addMinutes(MediaUrlResolver::SIGNED_URL_MINUTES),
            );
        } catch (Throwable) {
            return null;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ChangeRequestStatus;
use App\Enums\DuplicateStatus;
use App\Enums\VerificationStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * What the archive holds, and what needs a human.
 *
 * Counts come from indexed columns and denormalised counters rather than from
 * walking the graph — a dashboard must never be the reason a page is slow.
 * Cached briefly, because it is the first thing every admin loads.
 */
class ArchiveOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $counts = Cache::remember('admin:overview', 60, fn () => $this->counts());

        return [
            Stat::make('People', number_format($counts['people']))
                ->description(number_format($counts['living']).' living · '.number_format($counts['deceased']).' deceased')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Relationships', number_format($counts['relationships']))
                ->description(number_format($counts['unions']).' unions')
                ->descriptionIcon('heroicon-m-arrows-right-left'),

            Stat::make('Structure', number_format($counts['tribes']).' tribes')
                ->description(number_format($counts['clans']).' clans · '.number_format($counts['branches']).' branches')
                ->descriptionIcon('heroicon-m-rectangle-group'),

            Stat::make('Verified', number_format($counts['verified']))
                ->description($counts['people'] > 0
                    ? number_format($counts['verified'] / $counts['people'] * 100, 1).'% of people'
                    : 'No people yet')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            // The two numbers that mean somebody has work to do.
            Stat::make('Awaiting review', number_format($counts['pending']))
                ->description('Change requests in the queue')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($counts['pending'] > 0 ? 'warning' : 'gray'),

            Stat::make('Possible duplicates', number_format($counts['duplicates']))
                ->description('Open, awaiting a merge decision')
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color($counts['duplicates'] > 0 ? 'warning' : 'gray'),
        ];
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $people = DB::table('people')->whereNull('deleted_at')->whereNull('merged_into_person_id');

        return [
            'people' => (clone $people)->count(),
            'living' => (clone $people)->where('is_living', true)->count(),
            'deceased' => (clone $people)->where('is_living', false)->count(),
            'verified' => (clone $people)->where('verification_status', VerificationStatus::Verified->value)->count(),
            'relationships' => DB::table('relationships')->whereNull('deleted_at')->count(),
            'unions' => DB::table('unions')->whereNull('deleted_at')->count(),
            'tribes' => DB::table('tribes')->whereNull('deleted_at')->count(),
            'clans' => DB::table('clans')->whereNull('deleted_at')->count(),
            'branches' => DB::table('family_branches')->whereNull('deleted_at')->count(),
            'pending' => DB::table('change_requests')->where('status', ChangeRequestStatus::Pending->value)->count(),
            'duplicates' => DB::table('duplicate_candidates')->where('status', DuplicateStatus::Open->value)->count(),
        ];
    }
}

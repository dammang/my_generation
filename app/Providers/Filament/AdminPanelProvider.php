<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\ArchiveOverview;
use App\Filament\Widgets\RecentContributions;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Without this the user menu shows a name and offers nothing to do
            // with it: no way to change your own password, and no way to fix
            // the name every audit entry in the archive is signed with.
            //
            // isSimple: false renders it inside the panel, so leaving the page
            // does not mean leaving the navigation and finding your way back.
            ->profile(isSimple: false)
            // Changing an address is not the same as owning the new one. This
            // app refuses contributions from an unverified email, so applying
            // the change immediately would leave somebody verified against an
            // address they may never have had. Filament holds the change until
            // the new address confirms it.
            ->emailChangeVerification()
            ->brandName('My Generation')
            ->colors([
                'primary' => Color::Emerald,
            ])
            // A membership request writes a database notification and nothing
            // else — there is no web page in this app that lists it. Without
            // this, the only way an administrator finds a pending membership
            // is remembering to open its list; the bell is the actual place
            // it becomes visible.
            ->databaseNotifications()
            ->navigationGroups([
                'Genealogy',
                'Organisation',
                'Review',
                'Archive',
                'Administration',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                ArchiveOverview::class,
                RecentContributions::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

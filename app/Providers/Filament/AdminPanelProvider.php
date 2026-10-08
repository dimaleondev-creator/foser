<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use App\Filament\Widgets\ApplicationsMonthlyChart;
use App\Filament\Widgets\FoserStatsOverview;
use App\Filament\Widgets\FinancialStatsOverview;
use App\Filament\Widgets\ApplicationsAnnualChart;
use App\Filament\Widgets\ApplicationsDistributionChart;
use App\Filament\Widgets\FinancialComparisonChart;
use App\Filament\Widgets\FinancialProgramBreakdown;
use App\Filament\Widgets\ApplicationsByUniversityChart;
use App\Filament\Widgets\ApplicationsBySexChart;
use App\Filament\Widgets\ApplicationsByRegionChart;
use App\Filament\Widgets\CommissionOverview;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\SystemSetting;
use App\Http\Middleware\SetLocale;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('')
            ->brandLogo(function (): string {
                /** @var FilesystemAdapter $disk */
                $disk = Storage::disk('public');

                return $disk->url(SystemSetting::query()
                    ->where('key', 'site_logo')
                    ->where('is_public', true)
                    ->value('value') ?? 'site/logo-foser.png');
            })
            ->brandLogoHeight('3rem')
            ->login()
            ->multiFactorAuthentication(
                [app(AppAuthentication::class)],
                isRequired: function (): bool {
                    $user = Auth::user();
                    return $user instanceof User && $user->hasAnyRole([
                        'super_admin', 'admin', 'directeur_general', 'gestionnaire',
                        'agent_dossier', 'agent_finance', 'agent_recherche', 'agent_communication',
                    ]);
                },
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                FoserStatsOverview::class,
                FinancialStatsOverview::class,
                ApplicationsMonthlyChart::class,
                ApplicationsAnnualChart::class,
                ApplicationsDistributionChart::class,
                FinancialComparisonChart::class,
                FinancialProgramBreakdown::class,
                ApplicationsByUniversityChart::class,
                ApplicationsBySexChart::class,
                ApplicationsByRegionChart::class,
                CommissionOverview::class,
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

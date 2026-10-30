<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\EditOrganizationProfile;
use App\Filament\App\Widgets\MembersOverview;
use App\Filament\App\Widgets\RoleWelcome;
use App\Filament\Shared\Pages\EditProfile;
use App\Http\Middleware\EnsurePrivilegedUsersHaveMultiFactorAuthentication;
use App\Http\Middleware\SetOrganizationContext;
use App\Livewire\FormPreview;
use App\Models\Organization;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Organisation back-office: /app/{organization-slug}. Tenancy is Filament's
 * own; SetOrganizationContext then activates the BelongsToOrganization scope.
 */
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->brandName('LINK')
            ->login()
            ->passwordReset()
            ->profile(EditProfile::class, isSimple: false)
            // Required at panel level so the set-up page exists; the middleware
            // below only enforces it for orgadmins (and superadmins).
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable()->brandName('LINK'),
                EmailAuthentication::make(),
            ], isRequired: true)
            ->multiFactorAuthenticationRequiredMiddlewareName(EnsurePrivilegedUsersHaveMultiFactorAuthentication::class)
            ->tenant(Organization::class, slugAttribute: 'slug', ownershipRelationship: 'organization')
            ->tenantProfile(EditOrganizationProfile::class)
            ->tenantMiddleware([
                SetOrganizationContext::class,
            ], isPersistent: true)
            // Form preview drawn with the candidate stylesheet, in an iframe.
            ->authenticatedTenantRoutes(function (): void {
                Route::livewire('job-postings/{record}/form-preview', FormPreview::class)
                    ->middleware(EnsurePrivilegedUsersHaveMultiFactorAuthentication::class)
                    ->name('form-preview');
            })
            ->databaseNotifications()
            ->colors([
                'primary' => Color::Teal,
            ])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                RoleWelcome::class,
                MembersOverview::class,
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

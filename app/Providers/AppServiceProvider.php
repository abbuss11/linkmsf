<?php

namespace App\Providers;

use App\Listeners\RecordAuthenticationEvents;
use App\Services\AntiSpam\CaptchaVerifier;
use App\Services\AntiSpam\NullCaptchaVerifier;
use App\Services\AntiSpam\TurnstileCaptchaVerifier;
use App\Support\Tenancy\OrganizationContext;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: a fresh, empty context for every request and every queued job.
        $this->app->scoped(OrganizationContext::class);

        $this->app->bind(CaptchaVerifier::class, function () {
            return config('link.turnstile.enabled')
                ? new TurnstileCaptchaVerifier(
                    (string) config('link.turnstile.secret_key'),
                    (int) config('link.turnstile.timeout_seconds'),
                )
                : new NullCaptchaVerifier;
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        FilamentTimezone::set(config('link.display_timezone'));

        Event::subscribe(RecordAuthenticationEvents::class);

        $this->resetOrganizationContextAroundJobs();
        $this->configureRateLimiting();
    }

    /**
     * Belt and braces on top of the scoped binding: a job never inherits the
     * organisation of the previous job processed by the same worker.
     */
    private function resetOrganizationContextAroundJobs(): void
    {
        Event::listen(JobProcessing::class, fn () => app(OrganizationContext::class)->clear());
        Event::listen(JobProcessed::class, fn () => app(OrganizationContext::class)->clear());
    }

    private function configureRateLimiting(): void
    {
        $limits = config('link.rate_limits');

        RateLimiter::for('login', function (Request $request) use ($limits) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute($limits['login_per_minute'])->by('login:'.$email.'|'.$request->ip()),
                Limit::perMinute($limits['login_per_minute'] * 10)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('register', fn (Request $request) => Limit::perHour($limits['register_per_hour_per_ip'])
            ->by('register:'.$request->ip()));

        RateLimiter::for('password-reset', function (Request $request) use ($limits) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perHour($limits['password_reset_per_hour'])->by('reset:'.$email),
                Limit::perHour($limits['password_reset_per_hour'] * 10)->by('reset-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('organization-requests', fn (Request $request) => Limit::perHour($limits['organization_requests_per_hour_per_ip'])
            ->by('org-request:'.$request->ip()));

        RateLimiter::for('invitations', fn (Request $request) => Limit::perMinute($limits['invitation_accept_per_minute'])
            ->by('invitation:'.$request->ip()));
    }
}

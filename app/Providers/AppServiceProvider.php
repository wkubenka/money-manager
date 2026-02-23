<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        if (app()->isProduction()) {
            URL::forceScheme('https');

            // CloudFront doesn't forward X-Forwarded-Proto or X-Forwarded-Host,
            // causing a scheme/host mismatch in signed URL validation (Livewire file uploads).
            // Signed URLs are generated with https:// but the origin connection is http://.
            if (! request()->headers->has('X-Forwarded-Proto')) {
                request()->headers->set('X-Forwarded-Proto', 'https');
            }

            if (! request()->headers->has('X-Forwarded-Host')) {
                request()->headers->set(
                    'X-Forwarded-Host',
                    parse_url(config('app.url'), PHP_URL_HOST),
                );
            }
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}

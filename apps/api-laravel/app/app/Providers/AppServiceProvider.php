<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

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
        $this->configureRateLimits();
        $this->ensureRequiredConfig();
    }

    private function configureRateLimits(): void
    {
        RateLimiter::for('wa-webhooks', function (Request $request) {
            $limit = (int) config('services.wa_gateway.webhook_rate_limit', 120);

            if ($limit <= 0) {
                return Limit::none();
            }

            return Limit::perMinute($limit)->by($request->ip() ?? 'wa-webhook');
        });
    }

    private function ensureRequiredConfig(): void
    {
        if ($this->app->environment('testing')) {
            return;
        }

        $required = [
            'APP_KEY' => config('app.key'),
            'WEBHOOK_SECRET' => config('services.wa_gateway.webhook_secret'),
            'WA_GATEWAY_URL' => config('services.wa_gateway.send_url'),
            'WA_GATEWAY_TOKEN' => config('services.wa_gateway.token'),
        ];

        if (config('broadcasting.default') === 'reverb') {
            $required['REVERB_APP_ID'] = config('reverb.apps.apps.0.app_id');
            $required['REVERB_APP_KEY'] = config('reverb.apps.apps.0.key');
            $required['REVERB_APP_SECRET'] = config('reverb.apps.apps.0.secret');
        }

        $missing = array_keys(array_filter($required, static fn ($value) => empty($value)));

        if ($missing !== []) {
            throw new RuntimeException(
                'Missing required configuration: '.implode(', ', $missing)
            );
        }
    }
}

<?php

namespace App\Providers;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\StatusPage;
use App\Policies\MonitorPolicy;
use App\Policies\NotificationChannelPolicy;
use App\Policies\StatusPagePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Monitor::class => MonitorPolicy::class,
        NotificationChannel::class => NotificationChannelPolicy::class,
        StatusPage::class => StatusPagePolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->email . '|' . $request->ip());
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}

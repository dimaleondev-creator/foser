<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use App\Contracts\SmsProvider;
use App\Contracts\SearchService;
use App\Services\Search\PostgresSearchService;
use App\Services\Providers\NullSmsProvider;
use App\Contracts\AIProviderInterface;
use App\Services\AI\MockAIProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsProvider::class, NullSmsProvider::class);
        $this->app->bind(SearchService::class, PostgresSearchService::class);
        $this->app->bind(AIProviderInterface::class, MockAIProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        Gate::before(function (User $user): ?bool {
            return $user->hasRole('super_admin') ? true : null;
        });

        Gate::policy(User::class, UserPolicy::class);
    }
}

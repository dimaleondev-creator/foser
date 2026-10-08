<?php

namespace App\Providers;

use App\Models\User;
use App\Models\SystemSetting;
use App\Models\Partner;
use App\Models\News;
use App\Models\Event;
use App\Models\PressRelease;
use App\Models\Media;
use App\Models\MediaAlbum;
use App\Models\Testimonial;
use App\Models\CmsContent;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Observers\CommunicationContentObserver;
use App\Policies\UserPolicy;
use App\Models\University;
use App\Policies\UniversityPolicy;
use App\Models\Commission;
use App\Policies\CommissionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Contracts\SmsProvider;
use App\Contracts\SearchService;
use App\Services\Search\PostgresSearchService;
use App\Services\Providers\NullSmsProvider;
use App\Contracts\AIProviderInterface;
use App\Services\AI\MockAIProvider;
use App\Services\AIService;
use App\Http\Responses\FilamentLoginResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsProvider::class, NullSmsProvider::class);
        $this->app->bind(SearchService::class, PostgresSearchService::class);
        $this->app->bind(AIProviderInterface::class, AIService::class);
        $this->app->bind(LoginResponseContract::class, FilamentLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([News::class, Event::class, PressRelease::class, Media::class, MediaAlbum::class, Partner::class, Testimonial::class, CmsContent::class, NewsletterCampaign::class, NewsletterSubscriber::class] as $contentModel) {
            $contentModel::observe(CommunicationContentObserver::class);
        }

        View::composer('components.site-logo-mark', function ($view): void {
            $siteLogo = Cache::remember('public_site_logo_path', now()->addMinutes(5), fn () => SystemSetting::query()
                ->where('key', 'site_logo')
                ->where('type', 'image')
                ->where('is_public', true)
                ->value('value'));

            $view->with('siteLogo', $siteLogo);
        });

        View::composer('components.site-partners-footer', function ($view): void {
            $footerPartners = Partner::query()
                ->where('status', 'published')
                ->whereIn('display_location', ['footer', 'both'])
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->limit(8)
                ->get(['name', 'slug', 'logo_path']);

            $view->with('footerPartners', $footerPartners);
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });
        RateLimiter::for('ine-search', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('ine-otp-send', fn (Request $request): Limit => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('ine-otp-verify', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('ine-declaration', fn (Request $request): Limit => Limit::perMinute(6)->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        Gate::before(function (User $user): ?bool {
            return $user->hasRole('super_admin') ? true : null;
        });

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(University::class, UniversityPolicy::class);
        Gate::policy(Commission::class, CommissionPolicy::class);
    }
}

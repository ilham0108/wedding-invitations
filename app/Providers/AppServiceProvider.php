<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\User;
use App\Services\SiteContentService;
use App\Services\Subscription\SubscriptionLimitService;
use App\Services\SystemSettings;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Memoizes plan lookups for one request or queued job.
        $this->app->scoped(SubscriptionLimitService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->applySystemSettings();

        // Number::format() follows the app language: "1.000" in Indonesian, "1,000" in English.
        Number::useLocale($this->app->getLocale());
        Event::listen(LocaleUpdated::class, fn (LocaleUpdated $event) => Number::useLocale($event->locale));

        VerifyEmail::createUrlUsing(fn (User $user): string => URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $user->getRouteKey(), 'hash' => sha1($user->getEmailForVerification())],
        ));

        /**
         * Grant abilities that match a permission slug held by one of the user's roles.
         * Returning null lets model policies decide every other ability.
         */
        Gate::before(fn (User $user, string $ability): ?bool => $user->hasPermission($ability) ? true : null);

        // Footer and menu of the marketing site come from the admin CMS.
        View::composer('components.layouts.marketing', function (ViewInstance $view): void {
            $content = $this->app->make(SiteContentService::class)->all();

            $view->with([
                'siteContact' => $content['contact'],
                'hasFaq' => $content['faq']['items'] !== [],
                'footerPages' => Page::query()->published()->where('show_in_footer', true)->ordered()->get(['slug', 'title']),
            ]);
        });

        View::composer('components.layouts.app', function (ViewInstance $view): void {
            $user = Auth::user();

            $notifications = $user instanceof User
                ? $user->unreadNotifications()->latest()->limit(5)->get(['data', 'created_at'])
                    ->map(fn ($notification): array => [
                        'title' => (string) ($notification->data['title'] ?? ''),
                        'message' => (string) ($notification->data['message'] ?? ''),
                        'url' => (string) ($notification->data['url'] ?? '#'),
                        'time' => $notification->created_at->diffForHumans(),
                    ])
                    ->all()
                : [];

            $view->with([
                'user' => $user,
                'hasUnreadNotifications' => $notifications !== [],
                'notifications' => $notifications,
            ]);
        });
    }

    /**
     * Admin settings (/admin/settings) that override config. Skipped while the table does not
     * exist yet (fresh install, migrations).
     */
    private function applySystemSettings(): void
    {
        try {
            $settings = $this->app->make(SystemSettings::class)->all();
        } catch (Throwable) {
            return;
        }

        config([
            'app.name' => $settings['site_name'],
            'app.support_email' => $settings['support_email'],
        ]);
    }
}

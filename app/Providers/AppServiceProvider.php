<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\Plan;
use App\Models\User;
use App\Services\FontRegistry;
use App\Services\Invitation\DesignRegistry;
use App\Services\LogoService;
use App\Services\SiteContentService;
use App\Services\Subscription\SubscriptionLimitService;
use App\Services\SystemSettings;
use App\Services\TemplateCatalog;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
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
        $this->app->singleton(DesignRegistry::class);
        // Memoizes plan lookups for one request or queued job.
        $this->app->scoped(SubscriptionLimitService::class);
        // Memoizes the font list (built-in + installed) for one request.
        $this->app->scoped(FontRegistry::class);
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

        // In production every generated URL (email links, assets) uses APP_URL, never the request's Host header:
        // a forged Host must not put a password reset token on another domain (security audit, finding 1).
        if ($this->app->environment('production') && filled(config('app.url'))) {
            URL::forceRootUrl((string) config('app.url'));
            URL::forceScheme((string) parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https');
        }

        VerifyEmail::createUrlUsing(fn (User $user): string => URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $user->getRouteKey(), 'hash' => sha1($user->getEmailForVerification())],
        ));

        // The two emails the platform sends (resources/views/vendor/mail holds their branded layout).
        VerifyEmail::toMailUsing(fn (User $user, string $url): MailMessage => (new MailMessage)
            ->subject(__('Confirm your email address'))
            ->greeting(__('Hi :name,', ['name' => $user->name]))
            ->line(__('Thank you for signing up for :app. Confirm that this email address is yours to start making your invitations.', ['app' => config('app.name')]))
            ->action(__('Confirm email address'), $url)
            ->line(__('The link is valid for :count minutes.', ['count' => (int) config('auth.verification.expire', 60)]))
            ->line(__('Did not sign up? You can ignore this email; no account is activated without this confirmation.')));

        ResetPassword::toMailUsing(fn (User $user, string $token): MailMessage => (new MailMessage)
            ->subject(__('Reset your password'))
            ->greeting(__('Hi :name,', ['name' => $user->name]))
            ->line(__('We received a request to reset the password of your :app account.', ['app' => config('app.name')]))
            ->action(__('Choose a new password'), route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]))
            ->line(__('The link is valid for :count minutes and works once.', ['count' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60)]))
            ->line(__('Did not ask for this? Ignore this email: your password stays the same.')));

        /**
         * Grant abilities that match a permission slug held by one of the user's roles.
         * Returning null lets model policies decide every other ability.
         */
        // Section views mark the elements that have their own entrance: @animate('title'), or @animate('people', $loop->index)
        // inside a list. It reads the section being rendered ($section) and the page's theme animation (SectionAnimations).
        Blade::directive('animate', fn (string $expression): string => "<?php echo app(\\App\\Services\\Invitation\\SectionAnimations::class)->attributes(\$section ?? null, isset(\$invitation) ? (\$invitation->decoration['animation'] ?? null) : null, {$expression}); ?>");

        Gate::before(fn (User $user, string $ability): ?bool => $user->hasPermission($ability) ? true : null);

        // Footer and menu of the marketing site come from the admin CMS; an uploaded logo replaces the leaf wordmark.
        View::composer('components.layouts.marketing', function (ViewInstance $view): void {
            $content = $this->app->make(SiteContentService::class)->all();
            $hasTemplates = $this->app->make(TemplateCatalog::class)->publishedTemplates()->isNotEmpty();

            $view->with([
                'siteContact' => $content['contact'],
                // Menu links only for sections the home page actually renders.
                'homeSections' => [
                    'demo' => $hasTemplates,
                    'templates' => $hasTemplates,
                    'how-it-works' => $content['steps']['items'] !== [],
                    'features' => $content['features']['items'] !== [],
                    'pricing' => Plan::query()->active()->exists(),
                ],
                'hasFaq' => $content['faq']['items'] !== [],
                'hasUploadedLogo' => $this->app->make(LogoService::class)->exists(),
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

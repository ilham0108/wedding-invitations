<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\IllustrationController;
use App\Http\Controllers\Admin\MusicController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SiteContentController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\TemplateBuilderController;
use App\Http\Controllers\Admin\TemplateCategoryController;
use App\Http\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Http\Controllers\Admin\TemplatePackageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WhatsAppProviderController;
use App\Http\Controllers\Dashboard\GuestController;
use App\Http\Controllers\Dashboard\HomeController as DashboardHomeController;
use App\Http\Controllers\Dashboard\InvitationController;
use App\Http\Controllers\Dashboard\InvitationStoryController;
use App\Http\Controllers\Dashboard\NotificationController;
use App\Http\Controllers\Dashboard\RsvpController;
use App\Http\Controllers\Dashboard\SubscriptionController;
use App\Http\Controllers\Dashboard\TemplateController;
use App\Http\Controllers\Dashboard\WhatsAppController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationRsvpController;
use App\Http\Controllers\InvitationWishController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicInvitationController;
use App\Http\Controllers\TemplatePreviewController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/page/{page:slug}', [PageController::class, 'show'])->name('pages.show');

Route::get('/templates/{template}/preview', TemplatePreviewController::class)->name('templates.preview');
Route::get('/invitation/{slug}', [PublicInvitationController::class, 'show'])->name('invitations.public');
Route::post('/invitation/{slug}/rsvp', InvitationRsvpController::class)->middleware('throttle:10,1')->name('invitations.rsvp');
Route::post('/invitation/{slug}/wishes', InvitationWishController::class)->middleware('throttle:5,1')->name('invitations.wishes');
Route::get('/invitation/{slug}/{token}', [PublicInvitationController::class, 'personal'])
    ->middleware('throttle:60,1')
    ->name('invitations.personal');

// Provider delivery-status callbacks (signature-verified, CSRF-exempt in bootstrap/app.php).
Route::match(['get', 'post'], '/webhooks/whatsapp/{driver}', WhatsAppWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhooks.whatsapp');
Route::match(['get', 'post'], '/webhooks/whatsapp/{driver}/{provider}', WhatsAppWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhooks.whatsapp.connection');

// Payment gateway callbacks (token-verified, CSRF-exempt via webhooks/*).
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.payments');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/', DashboardHomeController::class)->name('dashboard');

        Route::name('dashboard.')->group(function () {
            Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');

            Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
            Route::get('/invitations/create', [InvitationController::class, 'create'])->name('invitations.create');
            Route::get('/invitations/{invitation}/edit', [InvitationController::class, 'edit'])->name('invitations.edit');
            Route::get('/invitations/{invitation}/preview', [InvitationController::class, 'preview'])->name('invitations.preview');
            Route::get('/invitations/{invitation}/story', InvitationStoryController::class)->name('invitations.story');
            Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

            Route::get('/guests', [GuestController::class, 'index'])->name('guests.index');
            Route::get('/guests/import-template', [GuestController::class, 'importTemplate'])->name('guests.import-template');
            Route::get('/invitations/{invitation}/guests/export', [GuestController::class, 'export'])->name('guests.export');

            Route::get('/rsvp', [RsvpController::class, 'index'])->name('rsvp.index');

            Route::get('/whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp.index');
            Route::get('/whatsapp/create', [WhatsAppController::class, 'create'])->name('whatsapp.create');
            Route::get('/whatsapp/templates', [WhatsAppController::class, 'templates'])->name('whatsapp.templates');
            Route::get('/whatsapp/connection', [WhatsAppController::class, 'connection'])->name('whatsapp.connection');
            Route::get('/whatsapp/campaigns/{campaign}', [WhatsAppController::class, 'show'])->name('whatsapp.campaigns.show');

            Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');

            Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
            Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->middleware('throttle:10,1')->name('subscription.checkout');
            Route::get('/subscription/checkout/{price}/method', [SubscriptionController::class, 'chooseMethod'])->name('subscription.method');
            Route::get('/subscription/orders/{order}', [SubscriptionController::class, 'showOrder'])->name('subscription.orders.show');
        });

        Route::get('/settings', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/settings', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/settings', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:super_admin')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/whatsapp', [WhatsAppProviderController::class, 'index'])->name('whatsapp.index');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/settings', [SystemSettingController::class, 'index'])->name('settings.index');
        Route::get('/content', [SiteContentController::class, 'index'])->name('content.index');
        Route::get('/templates', [AdminTemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/{template:ulid}/builder', [TemplateBuilderController::class, 'show'])->name('templates.builder');
        Route::get('/templates/{template:ulid}/export', [TemplatePackageController::class, 'export'])->name('templates.export');
        Route::get('/templates/{template:ulid}/builder/preview', [TemplateBuilderController::class, 'preview'])->name('templates.builder.preview');
        Route::get('/template-categories', [TemplateCategoryController::class, 'index'])->name('template-categories.index');
        Route::get('/illustrations', [IllustrationController::class, 'index'])->name('illustrations.index');
        Route::get('/music', [MusicController::class, 'index'])->name('music.index');
        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/payments', [OrderController::class, 'payments'])->name('payments.index');
    });
});

require __DIR__.'/auth.php';

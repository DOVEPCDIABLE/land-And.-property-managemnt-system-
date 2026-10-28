<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ExchangeWorkflowController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationalController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlatformSettingsController;
use App\Http\Controllers\PricingCalculatorController;
use App\Http\Controllers\PropertyAdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/professionals', [ExchangeWorkflowController::class, 'directory'])->name('services.directory');
Route::get('/pricing-calculator', [PricingCalculatorController::class, 'show'])->name('pricing.show');
Route::post('/pricing-calculator', [PricingCalculatorController::class, 'calculate'])->middleware('throttle:30,1')->name('pricing.calculate');
Route::get('/', [MarketplaceController::class, 'home'])->name('home');
Route::get('/company/{page}', [CompanyController::class, 'show'])->name('company.show');
Route::get('/properties', [MarketplaceController::class, 'properties'])->name('properties.index');
Route::get('/properties/{slug}', [MarketplaceController::class, 'property'])->name('properties.show');
Route::get('/journal/{slug}', [MarketplaceController::class, 'article'])->name('journal.show');
Route::get('/information/{page}', [MarketplaceController::class, 'info'])->name('information.show');
Route::post('/inquiries', [MarketplaceController::class, 'inquire'])->middleware('throttle:5,1')->name('inquiries.store');
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AccountController::class, 'registerForm'])->name('register');
    Route::post('/register', [AccountController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/webhooks/{provider}', WebhookController::class)->whereIn('provider', ['paystack', 'flutterwave', 'eversend'])->middleware('throttle:120,1')->name('webhooks.receive');
Route::middleware(['auth', 'auth.session', 'active', 'workspace'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/email/verify', fn () => view('auth.verify'))->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resend'])->middleware('throttle:3,1')->name('verification.send');
    Route::middleware('verified')->group(function (): void {
        Route::get('/operations/finance/report', [OperationalController::class, 'finance'])->name('operations.finance');
        Route::get('/operations/{entity}/export', [OperationalController::class, 'export'])->name('operations.export');
        Route::get('/operations/{entity}/incoming', [ExchangeWorkflowController::class, 'inbox'])->name('operations.incoming');
        Route::patch('/operations/{entity}/{record}/respond', [ExchangeWorkflowController::class, 'respond'])->whereNumber('record')->name('operations.respond');
        Route::get('/operations/{entity}', [OperationalController::class, 'index'])->name('operations.index');
        Route::get('/operations/{entity}/create', [OperationalController::class, 'create'])->name('operations.create');
        Route::post('/operations/{entity}', [OperationalController::class, 'store'])->name('operations.store');
        Route::get('/operations/{entity}/{record}', [OperationalController::class, 'show'])->whereNumber('record')->name('operations.show');
        Route::get('/operations/{entity}/{record}/edit', [OperationalController::class, 'edit'])->whereNumber('record')->name('operations.edit');
        Route::patch('/operations/{entity}/{record}', [OperationalController::class, 'update'])->whereNumber('record')->name('operations.update');
        Route::delete('/operations/{entity}/{record}', [OperationalController::class, 'destroy'])->whereNumber('record')->name('operations.destroy');
        Route::get('/catalog/site-media', [PropertyAdminController::class, 'library'])->name('properties.library');
        Route::post('/catalog/site-media/{media}', [PropertyAdminController::class, 'replace'])->name('properties.replace');
        Route::post('/catalog/import', [PropertyAdminController::class, 'import'])->name('properties.import');
        Route::post('/catalog/{property}/media/{media}', [PropertyAdminController::class, 'media'])->name('properties.media');
        Route::get('/private-documents/{document}', [OperationalController::class, 'download'])->name('documents.download');
        Route::post('/billing/invoices/{invoice}/status', [BillingController::class, 'transition'])->name('billing.transition');
        Route::post('/billing/invoices/{invoice}/receipts', [BillingController::class, 'receive'])->name('billing.receive');
        Route::get('/billing/invoices/{invoice}/print', [BillingController::class, 'invoice'])->name('billing.invoice');
        Route::get('/billing/receipts/{receipt}/print', [BillingController::class, 'receipt'])->name('billing.receipt');

        Route::get('/public-inquiries', [MarketplaceController::class, 'inquiries'])->middleware('permission:inquiries.view')->name('inquiries.index');

        Route::middleware('super_admin')->group(function (): void {
            Route::get('/system/settings', [PlatformSettingsController::class, 'edit'])->name('system.settings');
            Route::put('/system/settings', [PlatformSettingsController::class, 'update'])->name('system.settings.update');
            Route::post('/system/currencies', [PlatformSettingsController::class, 'currency'])->name('system.currencies.store');
            Route::post('/system/exchange-rates', [PlatformSettingsController::class, 'exchange'])->name('system.rates.store');
            Route::delete('/system/exchange-rates/{rate}', [PlatformSettingsController::class, 'removeRate'])->name('system.rates.destroy');
        });
        Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
        Route::get('/workspace/settings', [WorkspaceController::class, 'settings'])->middleware('permission:settings.view')->name('workspaces.settings');
        Route::patch('/workspace/settings', [WorkspaceController::class, 'update'])->middleware('permission:settings.manage')->name('workspaces.update');
        Route::get('/organisations', [OrganisationController::class, 'index'])->middleware('permission:organisations.view')->name('organisations.index');
        Route::post('/organisations', [OrganisationController::class, 'store'])->middleware('permission:organisations.manage')->name('organisations.store');
        Route::patch('/organisations/{agency}', [OrganisationController::class, 'update'])->middleware('permission:organisations.manage')->name('organisations.update');
        Route::post('/workspace/members', [MembershipController::class, 'store'])->middleware('permission:users.manage')->name('memberships.store');
        Route::get('/workspace/branches', [MembershipController::class, 'branches'])->middleware('permission:branches.view')->name('branches.index');
        Route::post('/workspace/branches', [MembershipController::class, 'branch'])->middleware('permission:branches.manage')->name('branches.store');
        Route::get('/workspace/audit', [WorkspaceController::class, 'audit'])->middleware('permission:audit.view')->name('audit.index');
        Route::get('/workspace/modules/{module}', [ModuleController::class, 'show'])->name('modules.show');
        Route::get('/account/lookup', [ModuleController::class, 'lookup'])->middleware('permission:users.lookup')->name('accounts.lookup');
        Route::get('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
        Route::patch('/account/profile', [AccountController::class, 'saveProfile'])->name('account.profile.update');
        Route::get('/account/security', [AccountController::class, 'security'])->name('account.security');
        Route::patch('/account/password', [AccountController::class, 'password'])->middleware('throttle:5,1')->name('account.password');
        Route::delete('/account/sessions', [AccountController::class, 'endSessions'])->middleware('throttle:5,1')->name('account.sessions');
        Route::get('/account/preferences', [AccountController::class, 'preferences'])->name('account.preferences');
        Route::patch('/account/preferences', [AccountController::class, 'savePreferences'])->name('account.preferences.update');
        Route::get('/dashboard', [PanelController::class, 'home'])->name('dashboard');
        Route::get('/panels/{panel}', [PanelController::class, 'show'])->name('panels.show');
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
        Route::middleware('permission:users.manage')->group(function (): void {
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        });
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('/notifications', [NotificationController::class, 'store'])->middleware('permission:notifications.send')->name('notifications.store');
        Route::middleware('permission:followups.view')->group(function (): void {
            Route::get('/follow-ups', [FollowUpController::class, 'index'])->name('followups.index');
            Route::post('/follow-ups', [FollowUpController::class, 'store'])->middleware('permission:followups.create')->name('followups.store');
            Route::patch('/follow-ups/{followup}/cancel', [FollowUpController::class, 'cancel'])->middleware('permission:followups.edit')->name('followups.cancel');
        });
        Route::middleware('permission:payments.view')->group(function (): void {
            Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::post('/payments', [PaymentController::class, 'store'])->middleware('permission:payments.create')->name('payments.store');
        });
        Route::get('/payments/return', [PaymentController::class, 'returned'])->name('payments.return');
        Route::get('/integrations', [PanelController::class, 'integrations'])->middleware('permission:integrations.view')->name('integrations.index');
    });
});

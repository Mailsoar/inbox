<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TestController;
// use App\Http\Controllers\Admin\DashboardController;
// use App\Http\Controllers\Admin\EmailAccountController;
// use App\Http\Controllers\Admin\TestAdminController;
// use App\Http\Controllers\Auth\GoogleAuthController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Page d'accueil avec formulaire de test
Route::get('/', [HomeController::class, 'index'])->name('home');

// Formulaire de création de test
Route::get('/test/create', [TestController::class, 'create'])->name('test.create');

// Redirect /public/test to home page
Route::get('/public/test', function () {
    return redirect()->route('home');
});

// Créer un nouveau test (POST)
Route::post('/test', [TestController::class, 'store'])->name('test.store');

// Check rate limits
Route::post('/test/check-limits', [TestController::class, 'checkLimits'])->name('test.check-limits');


// Voir les instructions du test
Route::get('/test/{unique_id}/instructions', [TestController::class, 'instructions'])->name('test.instructions');

// Voir les résultats d'un test
Route::get('/test/{unique_id}/results', [TestController::class, 'results'])->name('test.results');

// Annuler un test
Route::post('/test/{unique_id}/cancel', [TestController::class, 'cancel'])->name('test.cancel');

// Ancienne route pour compatibilité
Route::get('/test/{unique_id}', [TestController::class, 'show'])->name('test.show');

// API pour récupérer les mises à jour du test (SSE)
Route::get('/test/{unique_id}/stream', [TestController::class, 'stream'])->name('test.stream');

// Retrouver ses tests par email (avec vérification)
Route::match(['get', 'post'], '/my-tests', [TestController::class, 'requestAccess'])->name('test.request-access');
Route::match(['get', 'post'], '/my-tests/verify', [TestController::class, 'verifyCode'])->name('test.verify-code');
Route::get('/my-tests/authenticated', [TestController::class, 'myTestsAuthenticated'])->name('test.my-tests-authenticated');
Route::post('/my-tests/logout', [TestController::class, 'logout'])->name('test.logout');
Route::get('/my-tests/devices', [TestController::class, 'trustedDevices'])->name('test.trusted-devices');
Route::delete('/my-tests/devices/{device}', [TestController::class, 'removeTrustedDevice'])->name('test.remove-trusted-device');

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| CSRF Token Refresh Route
|--------------------------------------------------------------------------
*/

Route::get('/refresh-csrf', function() {
    return response()->json(['token' => csrf_token()]);
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

// Page de login admin
Route::get('/admin/login', function () {
    if (auth('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }
    return view('admin.login');
})->name('admin.login');

Route::prefix('admin/auth')->group(function () {
    Route::get('google', [App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('admin.auth.google');
    Route::get('google/callback', [App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->name('admin.auth.google.callback');
    Route::post('logout', [App\Http\Controllers\Auth\GoogleAuthController::class, 'logout'])->name('admin.logout');
});

// Route de compatibilité pour l'ancien callback Google
Route::get('/auth/google/callback', [App\Http\Controllers\Auth\GoogleAuthController::class, 'callback']);

/*
|--------------------------------------------------------------------------
| OAuth Routes for Email Accounts
|--------------------------------------------------------------------------
*/

// Gmail OAuth
Route::prefix('oauth/gmail')->group(function () {
    Route::get('connect', [App\Http\Controllers\Admin\OAuthController::class, 'redirectToGmail'])
        ->name('oauth.gmail.connect')
        ->middleware('admin.auth');
    Route::get('callback', [App\Http\Controllers\Admin\OAuthController::class, 'handleGmailCallback'])
        ->name('oauth.gmail.callback');
});

// Microsoft OAuth
Route::prefix('oauth/microsoft')->group(function () {
    Route::get('connect', [App\Http\Controllers\Admin\OAuthController::class, 'redirectToMicrosoft'])
        ->name('oauth.microsoft.connect')
        ->middleware('admin.auth');
    Route::get('callback', [App\Http\Controllers\Admin\OAuthController::class, 'handleMicrosoftCallback'])
        ->name('oauth.microsoft.callback');
});

// Route de debug session admin (DÉSACTIVÉE EN PRODUCTION)
// Route::get('/admin/debug-session', function () {
//     return [
//         'admin_check' => Auth::guard('admin')->check(),
//         'admin_user' => Auth::guard('admin')->user(),
//         'session_id' => session()->getId(),
//         'session_data' => session()->all(),
//         'guards' => [
//             'web' => Auth::guard('web')->check(),
//             'admin' => Auth::guard('admin')->check(),
//         ]
//     ];
// });

// Route temporaire pour test admin (DÉSACTIVÉE EN PRODUCTION)
// Route::get('/admin/quick-login/{email}', function ($email) {
//     if (!app()->environment('local', 'staging')) {
//         abort(404);
//     }
//     
//     $adminUser = App\Models\AdminUser::firstOrCreate(
//         ['email' => urldecode($email)],
//         [
//             'name' => explode('@', $email)[0],
//             'is_active' => true,
//         ]
//     );
//     
//     Auth::guard('admin')->login($adminUser);
//     
//     return redirect()->route('admin.dashboard');
// });

/*
|--------------------------------------------------------------------------
| Admin Routes (Protected)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware('admin.auth')->group(function () {
    // Dashboard
    Route::get('/', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('admin.dashboard');
    
    // Admin Users Management (Super Admin only)
    Route::get('users', [App\Http\Controllers\Admin\AdminUserController::class, 'index'])
        ->name('admin.users.index');
    Route::get('users/{admin}/edit', [App\Http\Controllers\Admin\AdminUserController::class, 'edit'])
        ->name('admin.users.edit');
    Route::put('users/{admin}', [App\Http\Controllers\Admin\AdminUserController::class, 'update'])
        ->name('admin.users.update');
    Route::post('users/{admin}/toggle-active', [App\Http\Controllers\Admin\AdminUserController::class, 'toggleActive'])
        ->name('admin.users.toggle-active');
    
    // Email Accounts Management (Admin & Super Admin)
    Route::resource('email-accounts', App\Http\Controllers\Admin\EmailAccountController::class)
        ->names('admin.email-accounts')
        ->except(['show', 'destroy'])
        ->middleware('permission:manage_email_accounts');
    
    // Delete email account (Super Admin only)
    Route::delete('email-accounts/{emailAccount}', [App\Http\Controllers\Admin\EmailAccountController::class, 'destroy'])
        ->name('admin.email-accounts.destroy')
        ->middleware('permission:delete_data');
    
    // Test connection
    Route::post('email-accounts/{emailAccount}/test', [App\Http\Controllers\Admin\EmailAccountController::class, 'testConnection'])
        ->name('admin.email-accounts.test');
    
    // MX Recommendation
    Route::post('email-accounts/mx-recommendation', [App\Http\Controllers\Admin\EmailAccountController::class, 'getMxRecommendation'])
        ->name('admin.email-accounts.mx-recommendation');
    
    // Anti-spam configuration
    Route::get('email-accounts/{emailAccount}/configure-antispam', [App\Http\Controllers\Admin\EmailAccountController::class, 'configureAntispam'])
        ->name('admin.email-accounts.configure-antispam');
    Route::post('email-accounts/{emailAccount}/configure-antispam', [App\Http\Controllers\Admin\EmailAccountController::class, 'saveAntispamConfig'])
        ->name('admin.email-accounts.save-antispam');
    Route::post('email-accounts/{emailAccount}/analyze-folder', [App\Http\Controllers\Admin\EmailAccountController::class, 'analyzeFolder'])
        ->name('admin.email-accounts.analyze-folder');
    
    // Password update
    Route::patch('email-accounts/{emailAccount}/update-password', [App\Http\Controllers\Admin\EmailAccountController::class, 'updatePassword'])
        ->name('admin.email-accounts.update-password');
    
    // Toggle account status
    Route::patch('email-accounts/{emailAccount}/toggle-status', [App\Http\Controllers\Admin\EmailAccountController::class, 'toggleStatus'])
        ->name('admin.email-accounts.toggle-status');
    
    // OAuth routes with account ID
    Route::get('email-accounts/oauth/{provider}', function($provider, Request $request) {
        $accountId = $request->get('account_id');
        session(['oauth_account_id' => $accountId]);
        
        // Redirect to the appropriate OAuth route
        if ($provider === 'gmail') {
            return redirect()->route('oauth.gmail');
        } elseif ($provider === 'outlook' || $provider === 'microsoft') {
            return redirect()->route('oauth.microsoft');
        }
        
        return redirect()->route('admin.email-accounts.index')
            ->with('error', 'Provider OAuth non supporté');
    })->name('admin.email-accounts.oauth');
    
    // Antispam Systems Management
    Route::resource('antispam-systems', App\Http\Controllers\Admin\AntispamSystemController::class)
        ->names('admin.antispam-systems');
    Route::post('antispam-systems/test-patterns', [App\Http\Controllers\Admin\AntispamSystemController::class, 'testPatterns'])
        ->name('admin.antispam-systems.test-patterns');
    
    // Unified Providers Management (Admin & Super Admin only)
    Route::resource('providers', App\Http\Controllers\Admin\ProviderController::class)
        ->names('admin.providers')
        ->except(['destroy'])
        ->middleware('permission:manage_providers');
    Route::delete('providers/{provider}', [App\Http\Controllers\Admin\ProviderController::class, 'destroy'])
        ->name('admin.providers.destroy')
        ->middleware('permission:delete_data');
    Route::post('providers/{provider}/test', [App\Http\Controllers\Admin\ProviderController::class, 'test'])
        ->name('admin.providers.test')
        ->middleware('permission:manage_providers');
    
    // Routes de compatibilité (redirection vers les nouvelles routes)
    Route::get('imap-providers', function() {
        return redirect()->route('admin.providers.index');
    });
    Route::get('email-providers', function() {
        return redirect()->route('admin.providers.index');
    });
    
    // Tests Management
    Route::get('tests', [App\Http\Controllers\Admin\TestAdminController::class, 'index'])
        ->name('admin.tests.index')
        ->middleware('permission:view_all');
    Route::get('tests/{test}', [App\Http\Controllers\Admin\TestAdminController::class, 'show'])
        ->name('admin.tests.show')
        ->middleware('permission:view_all');
    Route::delete('tests/{test}', [App\Http\Controllers\Admin\TestAdminController::class, 'destroy'])
        ->name('admin.tests.destroy')
        ->middleware('permission:delete_data');
    Route::patch('tests/{test}/force-recheck', [App\Http\Controllers\Admin\TestAdminController::class, 'forceRecheck'])
        ->name('admin.tests.force-recheck')
        ->middleware('permission:manage_tests');
    Route::patch('tests/{test}/cancel', [App\Http\Controllers\Admin\TestAdminController::class, 'cancel'])
        ->name('admin.tests.cancel')
        ->middleware('permission:manage_tests');
    
    // Queue Management (Admin & Super Admin only)
    Route::get('queue', [App\Http\Controllers\Admin\QueueStatusController::class, 'index'])
        ->name('admin.queue.index')
        ->middleware('permission:view_logs');
    Route::post('queue/process', [App\Http\Controllers\Admin\QueueStatusController::class, 'process'])
        ->name('admin.queue.process')
        ->middleware('permission:run_commands');
    Route::post('queue/retry/{id}', [App\Http\Controllers\Admin\QueueStatusController::class, 'retry'])
        ->name('admin.queue.retry')
        ->middleware('permission:manage_tests');
    Route::delete('queue/cancel/{id}', [App\Http\Controllers\Admin\QueueStatusController::class, 'cancel'])
        ->name('admin.queue.cancel')
        ->middleware('permission:manage_tests');
    Route::delete('queue/failed/{id}', [App\Http\Controllers\Admin\QueueStatusController::class, 'deleteFailed'])
        ->name('admin.queue.delete-failed')
        ->middleware('permission:delete_data');
    Route::delete('queue/clear/{queue}', [App\Http\Controllers\Admin\QueueStatusController::class, 'clearQueue'])
        ->name('admin.queue.clear')
        ->middleware('permission:delete_data');
    Route::delete('queue/clear-failed', [App\Http\Controllers\Admin\QueueStatusController::class, 'clearFailed'])
        ->name('admin.queue.clear-failed')
        ->middleware('permission:delete_data');
    
    // Filter Rules Management
    Route::resource('filter-rules', App\Http\Controllers\Admin\FilterRuleController::class)
        ->names('admin.filter-rules');
    Route::patch('filter-rules/{filterRule}/toggle', [App\Http\Controllers\Admin\FilterRuleController::class, 'toggle'])
        ->name('admin.filter-rules.toggle');
    Route::post('filter-rules/test', [App\Http\Controllers\Admin\FilterRuleController::class, 'test'])
        ->name('admin.filter-rules.test');
    
    // Email details
    Route::get('emails/{receivedEmail}', [App\Http\Controllers\Admin\TestAdminController::class, 'emailDetail'])
        ->name('admin.emails.show');
    
    // System Logs (Admin & Super Admin only)
    Route::get('logs', [App\Http\Controllers\Admin\LogsController::class, 'index'])
        ->name('admin.logs.index')
        ->middleware('permission:view_logs');
    Route::post('logs/clear', [App\Http\Controllers\Admin\LogsController::class, 'clear'])
        ->name('admin.logs.clear')
        ->middleware('permission:system_config');
});

/*
|--------------------------------------------------------------------------
| Fallback Route - Must be last
|--------------------------------------------------------------------------
*/

// Catch all undefined routes and redirect to home page
Route::fallback(function () {
    return redirect()->route('home');
});

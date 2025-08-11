<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Services\OAuthTokenService;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    /**
     * Redirect to Gmail OAuth
     */
    public function redirectToGmail(Request $request)
    {
        // Si un account_id est fourni, on réauthentifie un compte existant
        if ($request->has('account_id')) {
            $account = EmailAccount::findOrFail($request->account_id);
            session(['oauth_reauth_account_id' => $account->id]);
            
            Log::info('Starting OAuth re-authentication for Gmail', [
                'account_id' => $account->id,
                'email' => $account->email
            ]);
        }
        
        // Générer un state unique pour éviter les attaques CSRF
        $state = Str::random(40);
        session(['oauth_state' => $state, 'oauth_provider' => 'gmail']);

        return Socialite::driver('gmail')
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
                'login_hint' => isset($account) ? $account->email : null
            ])
            ->stateless()
            ->redirect();
    }

    /**
     * Handle Gmail OAuth callback
     */
    public function handleGmailCallback(Request $request)
    {
        Log::info('Gmail OAuth callback started', [
            'request_url' => $request->fullUrl(),
            'request_data' => $request->all(),
            'session_data' => session()->all(),
            'admin_auth' => auth('admin')->check(),
            'admin_user' => auth('admin')->user() ? auth('admin')->user()->email : null
        ]);
        
        try {
            $user = Socialite::driver('gmail')->stateless()->user();
            
            Log::info('Gmail user authenticated', [
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'has_token' => !empty($user->token),
                'has_refresh_token' => !empty($user->refreshToken),
                'expires_in' => $user->expiresIn ?? 'null'
            ]);

            // Vérifier si c'est une réauthentification
            $reauthAccountId = session('oauth_reauth_account_id');
            if ($reauthAccountId) {
                $emailAccount = EmailAccount::findOrFail($reauthAccountId);
                
                // Vérifier que l'email correspond
                if ($emailAccount->email !== $user->getEmail()) {
                    Log::warning('OAuth re-authentication email mismatch', [
                        'expected' => $emailAccount->email,
                        'received' => $user->getEmail()
                    ]);
                    
                    return redirect()
                        ->route('admin.email-accounts.edit', $emailAccount)
                        ->with('error', 'L\'email ne correspond pas au compte à réauthentifier.');
                }
                
                Log::info('Re-authenticating existing Gmail account', [
                    'account_id' => $emailAccount->id,
                    'email' => $emailAccount->email
                ]);
            } else {
                // Créer un compte temporaire NON ACTIF
                $emailAccount = EmailAccount::firstOrCreate(
                    ['email' => $user->getEmail()],
                    [
                        'provider' => 'gmail',
                        'name' => $user->getName(),
                        'auth_type' => 'oauth', // IMPORTANT: Définir auth_type pour OAuth
                        'is_active' => false, // IMPORTANT: Pas actif par défaut
                    ]
                );
            }

            // Sauvegarder les tokens OAuth
            $emailAccount->update([
                'auth_type' => 'oauth', // S'assurer que auth_type est bien oauth
                'oauth_token' => encrypt($user->token),
                'oauth_refresh_token' => $user->refreshToken ? encrypt($user->refreshToken) : null,
                'oauth_expires_at' => now()->addSeconds($user->expiresIn ?? 3600),
                'last_connection_check' => now(),
                'connection_status' => 'success',
            ]);

            Log::info('Gmail account saved', [
                'account_id' => $emailAccount->id,
                'email' => $emailAccount->email,
                'admin_check_before_redirect' => auth('admin')->check()
            ]);

            // Nettoyer la session OAuth
            session()->forget(['oauth_state', 'oauth_provider', 'oauth_reauth_account_id']);

            // Si pas d'utilisateur admin connecté, essayer de le rediriger vers la connexion admin
            if (!auth('admin')->check()) {
                Log::warning('No admin user authenticated during Gmail callback', [
                    'will_redirect_to' => route('admin.login')
                ]);
                
                // Stocker l'ID du compte pour après la connexion admin
                session(['pending_email_account_id' => $emailAccount->id]);
                
                return redirect()->route('admin.login')
                    ->with('info', 'Compte Gmail configuré. Veuillez vous connecter pour finaliser la configuration.');
            }

            // Rediriger vers la page d'édition pour finaliser la configuration
            Log::info('Redirecting to edit page', [
                'route' => route('admin.email-accounts.edit', $emailAccount),
                'account_id' => $emailAccount->id
            ]);

            return redirect()
                ->route('admin.email-accounts.edit', $emailAccount)
                ->with('success', 'Authentification Gmail réussie. Veuillez finaliser la configuration du compte.');

        } catch (\Exception $e) {
            Log::error('Gmail OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Si pas d'admin connecté, rediriger vers login
            if (!auth('admin')->check()) {
                return redirect()
                    ->route('admin.login')
                    ->with('error', 'Erreur lors de la connexion Gmail : ' . $e->getMessage());
            }

            return redirect()
                ->route('admin.email-accounts.create')
                ->with('error', 'Erreur lors de la connexion Gmail : ' . $e->getMessage());
        }
    }

    /**
     * Redirect to Microsoft OAuth
     */
    public function redirectToMicrosoft(Request $request)
    {
        // Si un account_id est fourni, on réauthentifie un compte existant
        if ($request->has('account_id')) {
            $account = EmailAccount::findOrFail($request->account_id);
            session(['oauth_reauth_account_id' => $account->id]);
            
            Log::info('Starting OAuth re-authentication for Microsoft', [
                'account_id' => $account->id,
                'email' => $account->email
            ]);
        }
        
        // Générer un state unique pour éviter les attaques CSRF
        $state = Str::random(40);
        session(['oauth_state' => $state, 'oauth_provider' => 'microsoft']);

        return Socialite::driver('microsoft')
            ->scopes([
                'https://outlook.office.com/IMAP.AccessAsUser.All',
                'https://outlook.office.com/SMTP.Send',
                'offline_access',
                'openid',
                'profile',
                'email'
            ])
            ->with([
                'prompt' => 'select_account',  // Force le choix du compte
                'access_type' => 'offline',
                'login_hint' => isset($account) ? $account->email : null
            ])
            ->stateless()
            ->redirect();
    }

    /**
     * Handle Microsoft OAuth callback
     */
    public function handleMicrosoftCallback(Request $request)
    {
        Log::info('Microsoft OAuth callback started', [
            'request_url' => $request->fullUrl(),
            'request_data' => $request->all(),
            'session_data' => session()->all(),
            'admin_auth' => auth('admin')->check(),
            'admin_user' => auth('admin')->user() ? auth('admin')->user()->email : null
        ]);

        try {
            $user = Socialite::driver('microsoft')->stateless()->user();

            Log::info('Microsoft user authenticated', [
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'has_token' => !empty($user->token),
                'has_refresh_token' => !empty($user->refreshToken),
                'expires_in' => $user->expiresIn ?? 'null'
            ]);

            // Vérifier si c'est une réauthentification
            $reauthAccountId = session('oauth_reauth_account_id');
            if ($reauthAccountId) {
                $emailAccount = EmailAccount::findOrFail($reauthAccountId);
                
                // Vérifier que l'email correspond
                if ($emailAccount->email !== $user->getEmail()) {
                    Log::warning('OAuth re-authentication email mismatch', [
                        'expected' => $emailAccount->email,
                        'received' => $user->getEmail()
                    ]);
                    
                    return redirect()
                        ->route('admin.email-accounts.edit', $emailAccount)
                        ->with('error', 'L\'email ne correspond pas au compte à réauthentifier.');
                }
                
                Log::info('Re-authenticating existing Microsoft account', [
                    'account_id' => $emailAccount->id,
                    'email' => $emailAccount->email
                ]);
            } else {
                // Créer un compte temporaire NON ACTIF
                $emailAccount = EmailAccount::firstOrCreate(
                    ['email' => $user->getEmail()],
                    [
                        'provider' => 'outlook',
                        'name' => $user->getName(),
                        'auth_type' => 'oauth', // IMPORTANT: Définir auth_type pour OAuth
                        'is_active' => false, // IMPORTANT: Pas actif par défaut
                    ]
                );
            }

            // Sauvegarder les tokens OAuth
            $emailAccount->update([
                'auth_type' => 'oauth', // S'assurer que auth_type est bien oauth
                'oauth_token' => encrypt($user->token),
                'oauth_refresh_token' => $user->refreshToken ? encrypt($user->refreshToken) : null,
                'oauth_expires_at' => now()->addSeconds($user->expiresIn ?? 3600),
                'last_connection_check' => now(),
                'connection_status' => 'success',
            ]);

            Log::info('Microsoft account saved', [
                'account_id' => $emailAccount->id,
                'email' => $emailAccount->email,
                'admin_check_before_redirect' => auth('admin')->check()
            ]);

            // IMPORTANT: Always force a token refresh to convert JWT to opaque token
            $oauthService = new OAuthTokenService();
            
            Log::info('Forcing token refresh to get opaque token', [
                'account_id' => $emailAccount->id,
                'email' => $emailAccount->email
            ]);
            
            // Force refresh to get opaque token (v1.0 endpoint)
            $refreshSuccess = $oauthService->refreshMicrosoftToken($emailAccount);
            
            if ($refreshSuccess) {
                Log::info('Token refreshed to opaque format', [
                    'account_id' => $emailAccount->id,
                    'email' => $emailAccount->email
                ]);
                
                // Reload account and test connection
                $emailAccount->refresh();
                $connectionWorking = $oauthService->testImapConnection($emailAccount);
                
                if ($connectionWorking) {
                    $emailAccount->update([
                        'connection_status' => 'success',
                        'last_connection_check' => now(),
                        'connection_error' => null
                    ]);
                    
                    Log::info('Microsoft account connection verified after refresh', [
                        'account_id' => $emailAccount->id,
                        'email' => $emailAccount->email
                    ]);
                } else {
                    Log::warning('Connection still failing after token refresh', [
                        'account_id' => $emailAccount->id,
                        'email' => $emailAccount->email
                    ]);
                    $connectionWorking = false;
                }
            } else {
                Log::error('Failed to refresh token to opaque format', [
                    'account_id' => $emailAccount->id,
                    'email' => $emailAccount->email
                ]);
                $connectionWorking = false;
            }

            // Nettoyer la session OAuth
            session()->forget(['oauth_state', 'oauth_provider', 'oauth_reauth_account_id']);

            // Si pas d'utilisateur admin connecté, essayer de le rediriger vers la connexion admin
            if (!auth('admin')->check()) {
                Log::warning('No admin user authenticated during Microsoft callback', [
                    'will_redirect_to' => route('admin.login')
                ]);
                
                // Stocker l'ID du compte pour après la connexion admin
                session(['pending_email_account_id' => $emailAccount->id]);
                
                return redirect()->route('admin.login')
                    ->with('info', 'Compte Microsoft configuré. Veuillez vous connecter pour finaliser la configuration.');
            }

            // Rediriger vers la page d'édition pour finaliser la configuration
            Log::info('Redirecting to edit page', [
                'route' => route('admin.email-accounts.edit', $emailAccount),
                'account_id' => $emailAccount->id
            ]);

            // Message based on connection status  
            $message = $connectionWorking 
                ? 'Authentification Microsoft réussie et connexion IMAP vérifiée. Veuillez finaliser la configuration du compte.'
                : 'Authentification Microsoft réussie mais connexion IMAP à vérifier. Veuillez finaliser la configuration du compte.';
            
            $messageType = $connectionWorking ? 'success' : 'warning';

            return redirect()
                ->route('admin.email-accounts.edit', $emailAccount)
                ->with($messageType, $message);

        } catch (\Exception $e) {
            Log::error('Microsoft OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Si pas d'admin connecté, rediriger vers login
            if (!auth('admin')->check()) {
                return redirect()
                    ->route('admin.login')
                    ->with('error', 'Erreur lors de la connexion Microsoft : ' . $e->getMessage());
            }

            return redirect()
                ->route('admin.email-accounts.create')
                ->with('error', 'Erreur lors de la connexion Microsoft : ' . $e->getMessage());
        }
    }
}
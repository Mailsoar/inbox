<?php

namespace App\Services;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OAuthTokenService
{
    /**
     * Motif du dernier échec de rafraîchissement, lisible par l'appelant
     * (commande, alerte) au lieu d'un « Unknown connection error ».
     */
    public ?string $lastError = null;

    /** Motif d'échec renvoyé par le fournisseur : description, sinon code. */
    private function describeFailure($response): string
    {
        $data = $response->json() ?? [];
        $message = $data['error_description'] ?? $data['error'] ?? ('HTTP ' . $response->status());

        // Microsoft ajoute trace et horodatage : seule la première ligne compte
        return trim(strtok((string) $message, "\r\n"));
    }

    /** Refuse d'appeler le fournisseur sans identifiants d'application. */
    private function hasAppCredentials(string $service, EmailAccount $account): bool
    {
        if (config("services.{$service}.client_id") && config("services.{$service}.client_secret")) {
            return true;
        }

        $this->lastError = "Identifiants OAuth de l'application absents (services.{$service}.client_id / client_secret) : vérifier le .env";
        Log::error('OAuth app credentials missing', ['service' => $service, 'email' => $account->email]);

        return false;
    }

    /**
     * Refresh Gmail OAuth token
     */
    public function refreshGmailToken(EmailAccount $account): bool
    {
        $this->lastError = null;

        if (!$account->oauth_refresh_token) {
            $this->lastError = 'Aucun refresh token : reconnecter la boîte';
            Log::error('No refresh token available for Gmail account', ['email' => $account->email]);
            return false;
        }

        if (!$this->hasAppCredentials('gmail', $account)) {
            return false;
        }

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.gmail.client_id'),
                'client_secret' => config('services.gmail.client_secret'),
                'refresh_token' => decrypt($account->oauth_refresh_token),
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $account->update([
                    'oauth_token' => encrypt($data['access_token']),
                    'oauth_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                    'last_token_refresh' => now(),
                ]);

                Log::info('Gmail token refreshed successfully', ['email' => $account->email]);
                return true;
            }

            $this->lastError = $this->describeFailure($response);
            Log::error('Gmail token refresh failed', [
                'email' => $account->email,
                'response' => $response->body()
            ]);
            return false;

        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            Log::error('Exception during Gmail token refresh', [
                'email' => $account->email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Refresh Microsoft OAuth token
     */
    public function refreshMicrosoftToken(EmailAccount $account): bool
    {
        $this->lastError = null;

        if (!$account->oauth_refresh_token) {
            $this->lastError = 'Aucun refresh token : reconnecter la boîte';
            Log::error('No refresh token available for Microsoft account', ['email' => $account->email]);
            return false;
        }

        if (!$this->hasAppCredentials('microsoft', $account)) {
            return false;
        }

        try {
            $tenant = config('services.microsoft.tenant', 'common');
            // Use v2.0 endpoint which works with tokens from Socialite
            $tokenUrl = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";

            $response = Http::asForm()->post($tokenUrl, [
                'client_id' => config('services.microsoft.client_id'),
                'client_secret' => config('services.microsoft.client_secret'),
                'refresh_token' => decrypt($account->oauth_refresh_token),
                'grant_type' => 'refresh_token',
                'scope' => 'https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send offline_access openid profile email',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $updateData = [
                    'oauth_token' => encrypt($data['access_token']),
                    'oauth_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                    'last_token_refresh' => now(),
                ];

                // Microsoft sometimes returns a new refresh token
                if (isset($data['refresh_token'])) {
                    $updateData['oauth_refresh_token'] = encrypt($data['refresh_token']);
                }

                $account->update($updateData);

                Log::info('Microsoft token refreshed successfully', ['email' => $account->email]);
                return true;
            }

            $this->lastError = $this->describeFailure($response);
            Log::error('Microsoft token refresh failed', [
                'email' => $account->email,
                'response' => $response->body()
            ]);
            return false;

        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            Log::error('Exception during Microsoft token refresh', [
                'email' => $account->email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Test IMAP connection with current token
     */
    public function testImapConnection(EmailAccount $account): bool
    {
        if ($account->provider !== 'outlook' || !$account->oauth_token) {
            return false;
        }

        try {
            $token = decrypt($account->oauth_token);
            
            $config = [
                'host' => 'outlook.office365.com',
                'port' => 993,
                'encryption' => 'ssl',
                'validate_cert' => true,
                'username' => $account->email,
                'password' => $token,
                'protocol' => 'imap',
                'authentication' => 'oauth',
            ];
            
            $cm = new \Webklex\PHPIMAP\ClientManager();
            $client = $cm->make($config);
            $client->connect();
            $client->disconnect();
            
            Log::info('IMAP connection test successful', [
                'email' => $account->email,
                'account_id' => $account->id
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::warning('IMAP connection test failed', [
                'email' => $account->email,
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Check if token is JWT and convert to opaque if needed
     */
    public function ensureOpaqueToken(EmailAccount $account): bool
    {
        if ($account->provider !== 'outlook' || !$account->oauth_token) {
            return true;
        }

        $token = decrypt($account->oauth_token);
        
        // Check if token is JWT (starts with 'eyJ')
        if (substr($token, 0, 3) === 'eyJ') {
            Log::info('JWT token detected, converting to opaque token', [
                'email' => $account->email,
                'token_length' => strlen($token)
            ]);
            
            return $this->refreshMicrosoftToken($account);
        }
        
        return true;
    }

    /**
     * Test connection and repair if needed (for account creation)
     */
    public function testAndRepairConnection(EmailAccount $account): bool
    {
        if ($account->provider !== 'outlook') {
            return true;
        }

        Log::info('Testing and repairing Microsoft account connection', [
            'email' => $account->email,
            'account_id' => $account->id
        ]);

        // IMPORTANT: Check if token is JWT and convert it to opaque token first
        if ($account->oauth_token) {
            try {
                $token = decrypt($account->oauth_token);
                
                // Check if token is JWT (starts with 'eyJ')
                if (substr($token, 0, 3) === 'eyJ') {
                    Log::info('JWT token detected during test and repair, converting to opaque token', [
                        'email' => $account->email,
                        'token_length' => strlen($token)
                    ]);
                    
                    // Force refresh to get opaque token
                    if (!$this->refreshMicrosoftToken($account)) {
                        Log::error('Failed to convert JWT to opaque token', [
                            'email' => $account->email
                        ]);
                        return false;
                    }
                    
                    // Reload account data after refresh
                    $account->refresh();
                }
            } catch (\Exception $e) {
                Log::warning('Could not decrypt token for JWT check, attempting refresh anyway', [
                    'email' => $account->email,
                    'error' => $e->getMessage()
                ]);
                
                // If we can't decrypt, try to refresh anyway
                if (!$this->refreshMicrosoftToken($account)) {
                    Log::error('Failed to refresh token after decryption error', [
                        'email' => $account->email
                    ]);
                    return false;
                }
                
                // Reload account data after refresh
                $account->refresh();
            }
        }

        // Now test current connection
        if ($this->testImapConnection($account)) {
            $account->update([
                'connection_status' => 'success',
                'last_connection_check' => now(),
            ]);
            
            Log::info('Account connection is working', [
                'email' => $account->email,
                'account_id' => $account->id
            ]);
            
            return true;
        }

        // Connection failed, try to repair
        Log::info('Connection failed, attempting repair', [
            'email' => $account->email,
            'account_id' => $account->id
        ]);

        // Try refreshing the token
        if ($this->refreshMicrosoftToken($account)) {
            // Test again after refresh
            $account->refresh();
            
            if ($this->testImapConnection($account)) {
                $account->update([
                    'connection_status' => 'success',
                    'last_connection_check' => now(),
                ]);
                
                Log::info('Account connection repaired successfully', [
                    'email' => $account->email,
                    'account_id' => $account->id
                ]);
                
                return true;
            }
        }

        // Still failing
        $account->update([
            'connection_status' => 'failed',
            'last_connection_check' => now(),
        ]);
        
        Log::error('Failed to repair account connection', [
            'email' => $account->email,
            'account_id' => $account->id
        ]);

        return false;
    }

    /**
     * Check if token needs refresh and refresh if necessary
     */
    public function ensureValidToken(EmailAccount $account): bool
    {
        // Skip for non-OAuth providers
        if (!in_array($account->provider, ['gmail', 'outlook'])) {
            return true;
        }

        // For Microsoft accounts, check if we need to convert JWT to opaque token
        if ($account->provider === 'outlook') {
            $this->ensureOpaqueToken($account);
        }

        // Check if token is expired or will expire soon (5 minutes buffer)
        if (!$account->oauth_expires_at || Carbon::parse($account->oauth_expires_at)->subMinutes(5)->isPast()) {
            Log::info('Token needs refresh', [
                'email' => $account->email,
                'provider' => $account->provider,
                'expires_at' => $account->oauth_expires_at
            ]);

            if ($account->provider === 'gmail') {
                return $this->refreshGmailToken($account);
            } elseif ($account->provider === 'outlook') {
                return $this->refreshMicrosoftToken($account);
            }
        }

        return true;
    }

    /** Le jeton expire-t-il dans les 5 minutes (ou est-il inconnu) ? */
    public function needsRefresh(EmailAccount $account): bool
    {
        return !$account->oauth_expires_at
            || Carbon::parse($account->oauth_expires_at)->subMinutes(5)->isPast();
    }

    /**
     * Rafraîchit les jetons qui expirent bientôt.
     *
     * Renvoie un bilan exact : les jetons encore valides ne sont plus
     * comptés comme « rafraîchis », et chaque échec vient avec son motif.
     *
     * @return array{refreshed:int, failed:array<string,string>}
     */
    public function refreshExpiringTokens(): array
    {
        $stats = ['refreshed' => 0, 'failed' => []];

        // Marge de 30 minutes : la commande tourne toutes les 30 minutes
        $expiringAccounts = EmailAccount::whereIn('provider', ['gmail', 'outlook'])
            ->where('is_active', true)
            ->where('auth_type', 'oauth')
            ->where(function ($query) {
                $query->whereNull('oauth_expires_at')
                    ->orWhere('oauth_expires_at', '<=', now()->addMinutes(30));
            })
            ->get();

        foreach ($expiringAccounts as $account) {
            try {
                // Un rafraîchissement anticipé : on n'attend pas la marge de 5 min
                $ok = $account->provider === 'gmail'
                    ? $this->refreshGmailToken($account)
                    : $this->refreshMicrosoftToken($account);

                if ($ok) {
                    $stats['refreshed']++;
                } else {
                    $stats['failed'][$account->email] = $this->lastError ?? 'Échec inconnu';
                }
            } catch (\Exception $e) {
                $stats['failed'][$account->email] = $e->getMessage();
            }
        }

        if ($stats['failed']) {
            Log::error('OAuth token refresh failures', $stats['failed']);
        }

        return $stats;
    }
}

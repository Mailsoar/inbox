<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\EmailServiceFactory;
use App\Services\LoggerService;
use App\Services\OAuthTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\ConnectionErrorAlert;
use Carbon\Carbon;

class CheckEmailConnectionsCommand extends Command
{
    protected $signature = 'email:check-connections';
    protected $description = 'Check email account connections and disable failed accounts';
    
    private $logger;
    
    public function __construct()
    {
        parent::__construct();
        $this->logger = new LoggerService('email-connection-check');
    }
    
    public function handle()
    {
        $this->info('Starting email connection check...');
        $this->logger->info('Starting email connection check');
        
        $accounts = EmailAccount::where('is_active', true)->get();
        $totalAccounts = $accounts->count();
        $failedAccounts = [];
        $successCount = 0;
        
        $this->info("Checking {$totalAccounts} active accounts...");
        
        foreach ($accounts as $account) {
            try {
                $this->info("Checking account: {$account->email}");
                
                // Create email service for this account
                $emailService = EmailServiceFactory::make($account);
                
                // Test connection
                $result = $emailService->testConnection();
                
                if (!(isset($result['success']) ? $result['success'] : ($result['status'] ?? false))) {
                    throw new \Exception($result['error'] ?? 'Unknown connection error');
                }
                
                // Update last checked timestamp
                $account->last_connection_check = now();
                $account->connection_status = 'success';
                $account->connection_error = null;
                $account->save();
                
                $successCount++;
                $this->info("✓ Connection successful for: {$account->email}");
                $this->logger->info("Connection successful", ['email' => $account->email]);

                $this->recordSuccess($account);
                
            } catch (\Exception $e) {
                // Connection failed
                $errorMessage = $e->getMessage();
                $this->error("✗ Connection failed for {$account->email}: {$errorMessage}");
                
                // For Microsoft accounts, try to repair the connection first
                if ($account->provider === 'outlook' && $account->auth_type === 'oauth') {
                    $this->info("  🔧 Attempting automatic repair for Microsoft account...");
                    
                    $oauthService = new OAuthTokenService();
                    $repaired = $oauthService->testAndRepairConnection($account);
                    
                    if ($repaired) {
                        $this->info("  ✅ Connection repaired successfully!");
                        $this->logger->info("Connection repaired", ['email' => $account->email]);
                        $successCount++;

                        $this->recordSuccess($account);

                        continue; // Skip the rest, account is now working
                    } else {
                        // Le motif réel (ex. identifiants d'application absents)
                        // vaut mieux que « Unknown connection error ».
                        if ($oauthService->lastError) {
                            $errorMessage = $oauthService->lastError;
                        }
                        $this->error("  ❌ Automatic repair failed: {$errorMessage}");
                        $this->logger->warning("Automatic repair failed", ['email' => $account->email]);
                    }
                }
                
                // Le service Gmail ne remonte que « Unknown connection error » :
                // le refus de Google (ex. token révoqué) dit quoi faire.
                if ($account->provider === 'gmail' && $account->auth_type === 'oauth') {
                    $oauthService = new OAuthTokenService();

                    if (!$oauthService->refreshGmailToken($account) && $oauthService->lastError) {
                        $errorMessage = $oauthService->lastError;
                    }
                }

                $this->logger->error("Connection failed", [
                    'email' => $account->email,
                    'error' => $errorMessage,
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Update account status but DON'T disable it immediately for OAuth accounts
                if ($account->auth_type === 'oauth') {
                    // For OAuth accounts, just mark as failed but keep active
                    $account->last_connection_check = now();
                    $account->connection_status = 'failed';
                    $account->connection_error = substr($errorMessage, 0, 500);
                    $account->save();
                    
                    $this->warn("  ⚠️ OAuth account marked as failed but kept active");
                } else {
                    // For non-OAuth accounts, disable as before
                    $account->is_active = false;
                    $account->last_connection_check = now();
                    $account->connection_status = 'failed';
                    $account->connection_error = substr($errorMessage, 0, 500);
                    $account->disabled_at = now();
                    $account->disabled_reason = 'Connection check failed: ' . substr($errorMessage, 0, 200);
                    $account->save();
                }
                
                $failedAccounts[] = [
                    'account' => $account,
                    'error' => $errorMessage
                ];

                $this->recordFailure($account, $errorMessage);
            }
        }
        
        // Send alert if there are failed accounts
        if (!empty($failedAccounts)) {
            $this->sendAlertEmail($failedAccounts);
        }
        
        // Summary
        $failedCount = count($failedAccounts);
        $this->info("\nConnection check completed:");
        $this->info("- Total accounts checked: {$totalAccounts}");
        $this->info("- Successful connections: {$successCount}");
        $this->info("- Failed connections: {$failedCount}");
        
        $this->logger->info("Connection check completed", [
            'total' => $totalAccounts,
            'success' => $successCount,
            'failed' => $failedCount
        ]);
        
        return 0;
    }
    
    
    /**
     * Alerte Slack après deux échecs consécutifs (une erreur passagère du
     * fournisseur ne dérange personne), puis au plus une fois par 24 h tant
     * que la boîte reste déconnectée.
     */
    private function recordFailure(EmailAccount $account, string $error): void
    {
        $failures = Cache::get("connection-failures:{$account->id}", 0) + 1;
        Cache::forever("connection-failures:{$account->id}", $failures);

        if ($failures < 2 || !Cache::add("connection-alert:cooldown:{$account->id}", true, now()->addDay())) {
            return;
        }

        $sent = $this->notifySlack(
            ":warning: Mailbox disconnected: {$account->email} (" . $this->providerName($account) . ")\n"
            . 'Reason: ' . $this->escapeSlack(substr($error, 0, 300)) . "\n"
            . '<' . route('admin.email-accounts.edit', $account) . '|Reconnect it in the admin>'
        );

        if ($sent) {
            Cache::forever("connection-alert:down:{$account->id}", true);
        } else {
            // Message perdu : on retentera à la prochaine vérification.
            Cache::forget("connection-alert:cooldown:{$account->id}");
        }
    }

    /**
     * Remet le compteur à zéro et annonce le retour si la panne avait été
     * signalée. La pause de 24 h court toujours : une boîte instable
     * n'alerte pas plus d'une fois par jour.
     */
    private function recordSuccess(EmailAccount $account): void
    {
        Cache::forget("connection-failures:{$account->id}");

        if (Cache::pull("connection-alert:down:{$account->id}")) {
            $this->notifySlack(":white_check_mark: Mailbox back online: {$account->email} (" . $this->providerName($account) . ')');
        }
    }

    private function providerName(EmailAccount $account): string
    {
        return ['gmail' => 'Gmail', 'outlook' => 'Outlook'][$account->provider] ?? ucfirst((string) $account->provider);
    }

    /** Échappement imposé par Slack pour le texte mrkdwn. */
    private function escapeSlack(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }

    /**
     * Publie dans le canal technique, au format des autres outils
     * (« [AEDA] … »). Un échec Slack ne doit pas interrompre la vérification.
     */
    private function notifySlack(string $text): bool
    {
        $token = config('services.slack_feedback.bot_token');
        $channel = config('services.slack_feedback.alerts_channel_id');

        if (!$token || !$channel) {
            $this->logger->warning('Slack alert skipped: bot token or alerts channel not configured');
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $channel,
                    'text' => '[Inbox] ' . $text,
                    'unfurl_links' => false,
                ]);

            if ($response->json('ok')) {
                return true;
            }

            $this->logger->error('Slack alert failed', ['error' => $response->json('error', 'HTTP ' . $response->status())]);
        } catch (\Throwable $e) {
            $this->logger->error('Slack alert failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    private function sendAlertEmail($failedAccounts)
    {
        $this->logger->info("Sending alert email for failed accounts", [
            'count' => count($failedAccounts)
        ]);
        
        $adminEmails = array_filter(array_map('trim', explode(',', (string) config('mailsoar.alert_emails'))));

        if (empty($adminEmails)) {
            $this->logger->error("No admin emails configured for alerts (ADMIN_ALERT_EMAILS)");
            $this->warn("No alert recipients configured (ADMIN_ALERT_EMAILS)");
            return;
        }

        // La vérification tourne toutes les 20 minutes : on n'alerte que si
        // la liste des comptes en échec change, ou au plus toutes les 6 heures.
        $signature = collect($failedAccounts)->map(fn ($f) => $f['account']->email)->sort()->implode(',');
        if (Cache::get('connection-alert:last') === $signature) {
            $this->logger->info("Alert already sent for these accounts, skipping");
            return;
        }
        
        // Prepare alert data
        $alertData = [
            'failedAccounts' => $failedAccounts,
            'checkedAt' => now(),
            'totalFailed' => count($failedAccounts)
        ];
        
        try {
            // Send email to each admin
            foreach ($adminEmails as $adminEmail) {
                Mail::to($adminEmail)->send(new ConnectionErrorAlert($alertData));
            }
            
            Cache::put('connection-alert:last', $signature, now()->addHours(6));
            $this->logger->info("Alert emails sent successfully");
        } catch (\Exception $e) {
            $this->logger->error("Failed to send alert email", [
                'error' => $e->getMessage()
            ]);
        }
    }
}
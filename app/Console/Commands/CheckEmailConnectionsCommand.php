<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\EmailServiceFactory;
use App\Services\LoggerService;
use App\Services\OAuthTokenService;
use Illuminate\Console\Command;
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
                        continue; // Skip the rest, account is now working
                    } else {
                        $this->error("  ❌ Automatic repair failed");
                        $this->logger->warning("Automatic repair failed", ['email' => $account->email]);
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
    
    
    private function sendAlertEmail($failedAccounts)
    {
        $this->logger->info("Sending alert email for failed accounts", [
            'count' => count($failedAccounts)
        ]);
        
        // Get admin emails from config
        $adminEmails = explode(',', env('ADMIN_ALERT_EMAILS', env('ADMIN_EMAIL', '')));
        $adminEmails = array_filter(array_map('trim', $adminEmails));
        
        if (empty($adminEmails)) {
            $this->logger->warning("No admin emails configured for alerts");
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
            
            $this->logger->info("Alert emails sent successfully");
        } catch (\Exception $e) {
            $this->logger->error("Failed to send alert email", [
                'error' => $e->getMessage()
            ]);
        }
    }
}
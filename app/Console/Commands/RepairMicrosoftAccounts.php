<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailAccount;
use App\Services\OAuthTokenService;
use Illuminate\Support\Facades\Log;

class RepairMicrosoftAccounts extends Command
{
    protected $signature = 'microsoft:repair-accounts 
                            {--dry-run : Run in dry-run mode without making changes}
                            {--force : Force repair even for recently checked accounts}';

    protected $description = 'Repair Microsoft accounts with authentication issues';

    protected OAuthTokenService $tokenService;

    public function __construct(OAuthTokenService $tokenService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        $this->info('Searching for Microsoft accounts with issues...');
        
        // Find Microsoft accounts with connection issues
        $query = EmailAccount::whereIn('provider', ['outlook', 'microsoft'])
            ->where(function ($q) {
                $q->where('connection_status', 'error')
                    ->orWhere('connection_status', 'authentication_failed')
                    ->orWhereNull('connection_status');
            });
        
        if (!$force) {
            // Skip recently checked accounts (within last hour)
            $query->where(function ($q) {
                $q->whereNull('last_connection_check')
                    ->orWhere('last_connection_check', '<', now()->subHour());
            });
        }
        
        $accounts = $query->get();
        
        if ($accounts->isEmpty()) {
            $this->info('No Microsoft accounts with issues found.');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$accounts->count()} accounts to repair");
        
        foreach ($accounts as $account) {
            $this->line("Processing: {$account->email}");
            
            if ($dryRun) {
                $this->info("  [DRY RUN] Would attempt to repair account");
                continue;
            }
            
            try {
                // Attempt to refresh token
                if ($account->refresh_token) {
                    $this->info("  Refreshing OAuth token...");
                    $result = $this->tokenService->refreshToken($account);
                    
                    if ($result) {
                        $account->update([
                            'connection_status' => 'connected',
                            'connection_error' => null,
                            'last_connection_check' => now(),
                        ]);
                        $this->info("  ✓ Token refreshed successfully");
                    } else {
                        $account->update([
                            'connection_status' => 'authentication_failed',
                            'connection_error' => 'Token refresh failed',
                            'last_connection_check' => now(),
                        ]);
                        $this->warn("  ✗ Token refresh failed");
                    }
                } else {
                    $this->warn("  ✗ No refresh token available");
                    $account->update([
                        'connection_status' => 'authentication_failed',
                        'connection_error' => 'No refresh token',
                        'last_connection_check' => now(),
                    ]);
                }
                
            } catch (\Exception $e) {
                $this->error("  ✗ Error: " . $e->getMessage());
                $account->update([
                    'connection_status' => 'error',
                    'connection_error' => $e->getMessage(),
                    'last_connection_check' => now(),
                ]);
                
                Log::error('Microsoft account repair failed', [
                    'account_id' => $account->id,
                    'email' => $account->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $this->info('Repair process completed');
        return Command::SUCCESS;
    }
}
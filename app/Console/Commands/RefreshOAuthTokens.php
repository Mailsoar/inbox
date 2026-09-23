<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OAuthTokenService;

class RefreshOAuthTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oauth:refresh-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh expiring OAuth tokens for email accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting OAuth token refresh...');
        
        $tokenService = new OAuthTokenService();
        $stats = $tokenService->refreshExpiringTokens();

        $this->info("Refreshed {$stats['refreshed']} tokens.");

        foreach ($stats['failed'] as $email => $error) {
            $this->error("Failed: {$email} — {$error}");
        }

        return $stats['failed'] ? Command::FAILURE : Command::SUCCESS;
    }
}
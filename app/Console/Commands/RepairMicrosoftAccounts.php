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

        // Comptes en échec (quel que soit le libellé historique du statut)
        // ou dont le jeton a expiré sans être renouvelé.
        $query = EmailAccount::where('provider', 'outlook')
            ->where('auth_type', 'oauth')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('connection_status', ['failed', 'error', 'authentication_failed'])
                    ->orWhereNull('connection_status')
                    ->orWhere('oauth_expires_at', '<', now());
            });

        if (!$force) {
            // Évite de retenter un compte renouvelé il y a quelques minutes
            $query->where(function ($q) {
                $q->whereNull('last_token_refresh')
                    ->orWhere('last_token_refresh', '<', now()->subMinutes(10));
            });
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('No Microsoft accounts with issues found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$accounts->count()} accounts to repair");
        $failures = 0;

        foreach ($accounts as $account) {
            $this->line("Processing: {$account->email} (status: " . ($account->connection_status ?? 'none') . ')');

            if ($dryRun) {
                $this->info('  [DRY RUN] Would attempt to repair account');
                continue;
            }

            if ($this->tokenService->refreshMicrosoftToken($account)) {
                $account->update([
                    'connection_status' => 'success',
                    'connection_error' => null,
                    'last_connection_check' => now(),
                ]);
                $this->info('  ✓ Token refreshed successfully');
                continue;
            }

            $failures++;
            $error = $this->tokenService->lastError ?? 'Token refresh failed';

            $account->update([
                'connection_status' => 'failed',
                'connection_error' => mb_substr($error, 0, 500),
                'last_connection_check' => now(),
            ]);

            $this->error("  ✗ {$error}");
            Log::error('Microsoft account repair failed', [
                'account_id' => $account->id,
                'email' => $account->email,
                'error' => $error,
            ]);
        }

        $this->info('Repair process completed');

        return $failures ? Command::FAILURE : Command::SUCCESS;
    }
}

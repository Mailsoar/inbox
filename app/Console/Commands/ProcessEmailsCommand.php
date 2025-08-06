<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailAccount;
use App\Models\Test;
use App\Services\EmailProcessingOrchestrator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProcessEmailsCommand extends Command
{
    protected $signature = 'emails:process 
                            {--parallel=4 : Number of parallel workers}
                            {--timeout=300 : Timeout in seconds for each account}
                            {--account=* : Specific account IDs to process}
                            {--test= : Process a specific test ID}
                            {--force : Force processing even if test is timed out}
                            {--dry-run : Run without actually processing}
                            {--debug : Show detailed error messages}';

    protected $description = 'Process incoming emails for placement tests';

    private $orchestrator;
    private $startTime;
    private $runId;

    public function __construct(EmailProcessingOrchestrator $orchestrator)
    {
        parent::__construct();
        $this->orchestrator = $orchestrator;
    }

    public function handle()
    {
        $this->startTime = now();
        $this->runId = \Str::uuid()->toString();
        
        $this->info('🚀 Starting email processing orchestrator');
        $this->info("Run ID: {$this->runId}");
        $this->info("Time: {$this->startTime->format('Y-m-d H:i:s')} (Europe/Paris)");
        
        try {
            // D'abord, marquer les tests en timeout
            $this->updateTimedOutTests();
            
            // Log le début du traitement
            Log::info('Email processing started', [
                'run_id' => $this->runId,
                'parallel_workers' => $this->option('parallel'),
                'timeout' => $this->option('timeout')
            ]);

            // Récupérer les comptes à traiter
            $accounts = $this->getAccountsToProcess();
            
            if ($accounts->isEmpty()) {
                $this->info('No accounts need processing at this time.');
                Log::info('No accounts to process', ['run_id' => $this->runId]);
                return 0;
            }

            $this->info("Found {$accounts->count()} accounts to process");
            
            // Traiter les comptes
            $results = $this->orchestrator->processAccounts(
                $accounts,
                $this->runId,
                $this->option('parallel'),
                $this->option('timeout'),
                $this->option('dry-run')
            );

            // Afficher le résumé
            $this->displaySummary($results);
            
            // Log le résumé
            Log::info('Email processing completed', [
                'run_id' => $this->runId,
                'duration' => now()->diffInSeconds($this->startTime),
                'accounts_processed' => count($results),
                'total_emails_found' => collect($results)->sum('emails_found'),
                'total_errors' => collect($results)->sum('errors_count')
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error('Fatal error: ' . $e->getMessage());
            
            Log::error('Email processing failed', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
    }

    /**
     * Récupérer les comptes qui ont des tests en attente
     */
    private function getAccountsToProcess()
    {
        // Si un test spécifique est demandé
        if ($testId = $this->option('test')) {
            $test = Test::where('unique_id', $testId)->first();
            
            if (!$test) {
                $this->error("Test not found: {$testId}");
                return collect();
            }
            
            // Vérifier le timeout sauf si --force est utilisé
            if (!$this->option('force')) {
                $timeoutMinutes = config('mailsoar.email_check_timeout_minutes', 30);
                if ($test->created_at->addMinutes($timeoutMinutes)->isPast()) {
                    $this->warn("Test {$testId} has timed out. Use --force to process anyway.");
                    return collect();
                }
            }
            
            // Debug: voir tous les comptes du test
            $allAccounts = $test->emailAccounts()->where('is_active', true)->get();
            $this->info("Test has " . $allAccounts->count() . " active accounts total");
            
            // Filtrer ceux qui n'ont pas encore reçu
            $pendingAccounts = $test->emailAccounts()
                ->where('is_active', true)
                ->whereNull('test_email_accounts.received_at')
                ->get();
            
            $this->info("Found " . $pendingAccounts->count() . " accounts pending email reception");
            
            // Afficher les comptes déjà traités
            $processedAccounts = $test->emailAccounts()
                ->where('is_active', true)
                ->whereNotNull('test_email_accounts.received_at')
                ->get();
            
            if ($processedAccounts->count() > 0) {
                $this->info("Already processed accounts:");
                foreach ($processedAccounts as $acc) {
                    $this->info("  - {$acc->email} (received at: {$acc->pivot->received_at})");
                }
            }
            
            return $pendingAccounts;
        }
        
        // Si des comptes spécifiques sont demandés
        if ($accountIds = $this->option('account')) {
            return EmailAccount::whereIn('id', $accountIds)
                ->where('is_active', true)
                ->get();
        }

        // Récupérer les tests actifs qui attendent des résultats
        $timeoutMinutes = config('mailsoar.email_check_timeout_minutes', 30);
        $cutoffTime = now()->subMinutes($timeoutMinutes);

        // Tests qui sont en cours et pas encore timeout
        $activeTestIds = Test::whereIn('status', ['pending', 'in_progress'])
            ->where('created_at', '>', $cutoffTime)
            ->pluck('id');

        if ($activeTestIds->isEmpty()) {
            return collect();
        }

        // Récupérer les comptes qui sont liés à ces tests et qui n'ont pas encore reçu l'email
        $accountsNeedingCheck = EmailAccount::where('is_active', true)
            ->whereHas('tests', function ($query) use ($activeTestIds) {
                $query->whereIn('tests.id', $activeTestIds)
                    ->whereNull('test_email_accounts.received_at');
            })
            // Exclure les comptes en période de retry
            ->whereNotExists(function($query) {
                $query->select(DB::raw(1))
                    ->from('email_account_failures')
                    ->whereColumn('email_account_failures.email_account_id', 'email_accounts.id')
                    ->where('retry_after', '>', now());
            })
            ->get();

        return $accountsNeedingCheck;
    }

    /**
     * Mettre à jour les tests qui ont dépassé le timeout
     */
    private function updateTimedOutTests()
    {
        $timeoutMinutes = config('mailsoar.email_check_timeout_minutes', 30);
        $cutoffTime = now()->subMinutes($timeoutMinutes);
        
        // Trouver et mettre à jour les tests en timeout
        $timedOutTests = Test::whereIn('status', ['pending', 'in_progress'])
            ->where('created_at', '<=', $cutoffTime)
            ->get();
        
        if ($timedOutTests->isNotEmpty()) {
            foreach ($timedOutTests as $test) {
                $test->status = 'timeout';
                $test->save();
                
                // Compter les emails non reçus (qui sont maintenant en timeout)
                $timeoutCount = $test->emailAccounts()
                    ->wherePivot('email_received', false)
                    ->count();
                
                Log::info('Test marked as timeout', [
                    'test_id' => $test->unique_id,
                    'created_at' => $test->created_at->toDateTimeString(),
                    'timeout_after' => $timeoutMinutes . ' minutes',
                    'emails_marked_timeout' => $timeoutCount
                ]);
            }
            
            $this->info("Marked {$timedOutTests->count()} test(s) as timeout");
        }
    }
    
    /**
     * Afficher le résumé du traitement
     */
    private function displaySummary($results)
    {
        $duration = now()->diffInSeconds($this->startTime);
        
        $this->info("\n📊 Processing Summary");
        $this->info("═══════════════════════════════════════");
        $this->info("Duration: {$duration} seconds");
        $this->info("Accounts processed: " . count($results));
        
        $totalEmails = collect($results)->sum('emails_found');
        $totalErrors = collect($results)->sum('errors_count');
        
        $this->info("Total emails found: {$totalEmails}");
        $totalProcessed = collect($results)->sum('emails_processed');
        $this->info("Total emails processed: {$totalProcessed}");
        
        if ($totalErrors > 0) {
            $this->warn("Total errors: {$totalErrors}");
            
            // Afficher les comptes avec erreurs
            $failedAccounts = collect($results)->filter(function($r) {
                return $r['errors_count'] > 0;
            });
            
            if ($failedAccounts->isNotEmpty()) {
                $this->warn("\n⚠️  Accounts with errors:");
                foreach ($failedAccounts as $account) {
                    $this->warn("  - {$account['email']}: {$account['errors_count']} error(s), status: {$account['status']}");
                    
                    // Afficher les messages d'erreur en mode debug
                    if ($this->option('debug') && !empty($account['error_messages'])) {
                        foreach ($account['error_messages'] as $errorMsg) {
                            $this->error("    → " . $errorMsg);
                        }
                    }
                }
                
                if (!$this->option('debug')) {
                    $this->info("\n💡 Use --debug to see detailed error messages");
                }
            }
        }
        
        // Détails par provider
        $byProvider = collect($results)->groupBy('provider');
        foreach ($byProvider as $provider => $providerResults) {
            $emails = $providerResults->sum('emails_found');
            $errors = $providerResults->sum('errors_count');
            $avgTime = round($providerResults->avg('duration'), 2);
            
            $this->info("\n{$provider}:");
            $this->info("  - Accounts: " . $providerResults->count());
            $this->info("  - Emails found: {$emails}");
            if ($errors > 0) {
                $this->warn("  - Errors: {$errors}");
            }
            $this->info("  - Avg time: {$avgTime}s");
        }
        
        $this->info("\n✅ Processing complete!");
    }
}
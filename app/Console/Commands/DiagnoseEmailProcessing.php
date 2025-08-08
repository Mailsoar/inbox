<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Test;
use App\Models\EmailAccount;
use App\Jobs\ProcessEmailAddressJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DiagnoseEmailProcessing extends Command
{
    protected $signature = 'emails:diagnose 
                            {--test-id= : Specific test ID to diagnose}
                            {--check-jobs : Check job queue status}
                            {--check-connections : Check email account connections}';

    protected $description = 'Diagnose email processing system and identify issues';

    public function handle()
    {
        $this->info("==================================================");
        $this->info("Email Processing System Diagnosis");
        $this->info("Time: " . now()->format('Y-m-d H:i:s'));
        $this->info("==================================================\n");

        // 1. Check active tests
        $this->checkActiveTests();
        
        // 2. Check job queue status
        if ($this->option('check-jobs')) {
            $this->checkJobQueue();
        }
        
        // 3. Check email account connections
        if ($this->option('check-connections')) {
            $this->checkEmailConnections();
        }
        
        // 4. Specific test diagnosis
        if ($testId = $this->option('test-id')) {
            $this->diagnoseSpecificTest($testId);
        }
        
        // 5. Check system health
        $this->checkSystemHealth();
        
        return Command::SUCCESS;
    }
    
    protected function checkActiveTests()
    {
        $this->info("📋 ACTIVE TESTS STATUS");
        $this->info("------------------------");
        
        $activeTests = Test::whereIn('status', ['pending', 'in_progress'])
            ->where('timeout_at', '>', now())
            ->get();
        
        if ($activeTests->isEmpty()) {
            $this->warn("No active tests found.");
        } else {
            $this->info("Found {$activeTests->count()} active test(s):\n");
            
            foreach ($activeTests as $test) {
                $progress = $test->expected_emails > 0 
                    ? round(($test->received_emails / $test->expected_emails) * 100, 1)
                    : 0;
                    
                $timeRemaining = Carbon::parse($test->timeout_at)->diffForHumans();
                
                $this->info("  Test ID: {$test->unique_id}");
                $this->info("  - Status: {$test->status}");
                $this->info("  - Progress: {$test->received_emails}/{$test->expected_emails} emails ({$progress}%)");
                $this->info("  - Visitor: {$test->visitor_email}");
                $this->info("  - Timeout: {$timeRemaining}");
                
                // Check email accounts for this test
                $pendingAccounts = DB::table('test_email_accounts')
                    ->join('email_accounts', 'test_email_accounts.email_account_id', '=', 'email_accounts.id')
                    ->where('test_email_accounts.test_id', $test->id)
                    ->where('test_email_accounts.email_received', false)
                    ->select('email_accounts.email', 'email_accounts.provider')
                    ->get();
                
                if ($pendingAccounts->isNotEmpty()) {
                    $this->info("  - Waiting for emails from:");
                    foreach ($pendingAccounts as $account) {
                        $this->info("    • {$account->email} ({$account->provider})");
                    }
                }
                
                $this->info("");
            }
        }
        
        // Check timed out tests
        $timedOutTests = Test::where('status', 'timeout')
            ->where('updated_at', '>', now()->subHour())
            ->count();
        
        if ($timedOutTests > 0) {
            $this->warn("⚠️ {$timedOutTests} test(s) timed out in the last hour\n");
        }
    }
    
    protected function checkJobQueue()
    {
        $this->info("📦 JOB QUEUE STATUS");
        $this->info("------------------------");
        
        // Check jobs in email-addresses queue
        $readyJobs = DB::table('jobs')
            ->where('queue', 'email-addresses')
            ->where('available_at', '<=', now()->timestamp)
            ->count();
            
        $delayedJobs = DB::table('jobs')
            ->where('queue', 'email-addresses')
            ->where('available_at', '>', now()->timestamp)
            ->count();
        
        $this->info("Queue: email-addresses");
        $this->info("  - Ready jobs: {$readyJobs}");
        $this->info("  - Delayed jobs: {$delayedJobs}");
        
        // Show oldest job
        $oldestJob = DB::table('jobs')
            ->where('queue', 'email-addresses')
            ->orderBy('created_at')
            ->first();
        
        if ($oldestJob) {
            $age = Carbon::createFromTimestamp($oldestJob->created_at)->diffForHumans();
            $this->info("  - Oldest job: {$age}");
            
            // Try to decode job data
            try {
                $payload = json_decode($oldestJob->payload, true);
                if (isset($payload['displayName'])) {
                    $this->info("    Type: {$payload['displayName']}");
                }
            } catch (\Exception $e) {
                // Ignore decode errors
            }
        }
        
        $this->info("");
        
        // Check failed jobs
        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 0) {
            $this->error("❌ {$failedJobs} failed job(s) in failed_jobs table");
            
            $recentFailed = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(3)
                ->get();
            
            foreach ($recentFailed as $job) {
                $this->error("  - Failed at: {$job->failed_at}");
                $this->error("    Queue: {$job->queue}");
                if ($job->exception) {
                    $firstLine = strtok($job->exception, "\n");
                    $this->error("    Error: " . substr($firstLine, 0, 100));
                }
            }
            $this->info("");
        }
    }
    
    protected function checkEmailConnections()
    {
        $this->info("📧 EMAIL ACCOUNT STATUS");
        $this->info("------------------------");
        
        $accounts = EmailAccount::where('is_active', true)->get();
        
        $stats = [
            'total' => $accounts->count(),
            'connected' => 0,
            'failed' => 0,
            'rate_limited' => 0,
            'unknown' => 0
        ];
        
        foreach ($accounts as $account) {
            if ($account->connection_status === 'connected') {
                $stats['connected']++;
            } elseif (in_array($account->connection_status, ['error', 'authentication_failed', 'failed'])) {
                $stats['failed']++;
            } elseif ($account->backoff_until && $account->backoff_until > now()) {
                $stats['rate_limited']++;
            } else {
                $stats['unknown']++;
            }
        }
        
        $this->info("Total active accounts: {$stats['total']}");
        $this->info("  ✅ Connected: {$stats['connected']}");
        $this->info("  ❌ Failed: {$stats['failed']}");
        $this->info("  ⏳ Rate limited: {$stats['rate_limited']}");
        $this->info("  ❓ Unknown: {$stats['unknown']}");
        
        // Show problematic accounts
        if ($stats['failed'] > 0 || $stats['rate_limited'] > 0) {
            $this->info("\nProblematic accounts:");
            
            $problematic = $accounts->filter(function ($account) {
                return in_array($account->connection_status, ['error', 'authentication_failed', 'failed']) ||
                       ($account->backoff_until && $account->backoff_until > now());
            });
            
            foreach ($problematic as $account) {
                $this->warn("  - {$account->email} ({$account->provider})");
                if ($account->connection_error) {
                    $this->warn("    Error: " . substr($account->connection_error, 0, 80));
                }
                if ($account->backoff_until && $account->backoff_until > now()) {
                    $remaining = Carbon::parse($account->backoff_until)->diffForHumans();
                    $this->warn("    Rate limited until: {$remaining}");
                }
            }
        }
        
        $this->info("");
    }
    
    protected function diagnoseSpecificTest($testId)
    {
        $this->info("🔍 SPECIFIC TEST DIAGNOSIS");
        $this->info("------------------------");
        
        $test = Test::where('unique_id', $testId)->first();
        
        if (!$test) {
            $this->error("Test {$testId} not found");
            return;
        }
        
        $this->info("Test ID: {$test->unique_id}");
        $this->info("Status: {$test->status}");
        $this->info("Created: " . $test->created_at->diffForHumans());
        $this->info("Progress: {$test->received_emails}/{$test->expected_emails} emails");
        
        // Check test results
        $results = DB::table('test_results')
            ->join('email_accounts', 'test_results.email_account_id', '=', 'email_accounts.id')
            ->where('test_results.test_id', $test->id)
            ->select('email_accounts.email', 'email_accounts.provider', 'test_results.placement', 
                     'test_results.created_at')
            ->get();
        
        if ($results->isNotEmpty()) {
            $this->info("\nReceived emails:");
            foreach ($results as $result) {
                $this->info("  ✅ {$result->email} - {$result->placement} - " . 
                           Carbon::parse($result->created_at)->diffForHumans());
            }
        }
        
        // Check pending accounts
        $pending = DB::table('test_email_accounts')
            ->join('email_accounts', 'test_email_accounts.email_account_id', '=', 'email_accounts.id')
            ->where('test_email_accounts.test_id', $test->id)
            ->where('test_email_accounts.email_received', false)
            ->select('email_accounts.id', 'email_accounts.email', 'email_accounts.provider', 
                     'email_accounts.connection_status', 'email_accounts.backoff_until')
            ->get();
        
        if ($pending->isNotEmpty()) {
            $this->info("\nPending accounts:");
            foreach ($pending as $account) {
                $status = $account->connection_status ?? 'unknown';
                $this->warn("  ⏳ {$account->email} ({$account->provider}) - Status: {$status}");
                
                // Check if there's a job for this account
                $hasJob = DB::table('jobs')
                    ->where('queue', 'email-addresses')
                    ->where('payload', 'like', '%emailAccountId";i:' . $account->id . ';%')
                    ->exists();
                
                if ($hasJob) {
                    $this->info("     📦 Job queued for this account");
                } else {
                    $this->warn("     ⚠️ No job queued for this account");
                }
                
                if ($account->backoff_until && Carbon::parse($account->backoff_until) > now()) {
                    $this->warn("     ⏰ Rate limited until: " . 
                               Carbon::parse($account->backoff_until)->diffForHumans());
                }
            }
        }
        
        $this->info("");
    }
    
    protected function checkSystemHealth()
    {
        $this->info("🏥 SYSTEM HEALTH CHECK");
        $this->info("------------------------");
        
        // Check if cron is running
        $lastOptimizedRun = DB::table('jobs')
            ->where('queue', 'email-addresses')
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($lastOptimizedRun) {
            $lastRunTime = Carbon::createFromTimestamp($lastOptimizedRun->created_at);
            $minutesAgo = $lastRunTime->diffInMinutes(now());
            
            if ($minutesAgo > 5) {
                $this->warn("⚠️ No new jobs created in {$minutesAgo} minutes");
                $this->warn("   The cron job might not be running properly");
            } else {
                $this->info("✅ Jobs are being created (last: {$minutesAgo} minutes ago)");
            }
        } else {
            $this->error("❌ No jobs found in email-addresses queue");
        }
        
        // Check logs
        $logFile = storage_path('logs/email-processing-optimized.log');
        if (file_exists($logFile)) {
            $lastModified = Carbon::createFromTimestamp(filemtime($logFile));
            $hoursAgo = $lastModified->diffInHours(now());
            
            if ($hoursAgo > 1) {
                $this->warn("⚠️ Optimized processing log not updated in {$hoursAgo} hours");
            } else {
                $this->info("✅ Optimized processing log is recent");
            }
        }
        
        $queueLogFile = storage_path('logs/email-queue-processing.log');
        if (file_exists($queueLogFile)) {
            $lastModified = Carbon::createFromTimestamp(filemtime($queueLogFile));
            $minutesAgo = $lastModified->diffInMinutes(now());
            
            if ($minutesAgo > 5) {
                $this->warn("⚠️ Queue processing log not updated in {$minutesAgo} minutes");
            } else {
                $this->info("✅ Queue processing log is recent");
            }
        }
        
        $this->info("\n==================================================");
        $this->info("Diagnosis complete at " . now()->format('Y-m-d H:i:s'));
        $this->info("==================================================");
    }
}
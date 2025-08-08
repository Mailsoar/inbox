<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Test;
use App\Models\EmailAccount;
use App\Jobs\ProcessEmailAddressJob;
use App\Jobs\TestJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class DebugQueueIssues extends Command
{
    protected $signature = 'queue:debug 
                            {--test-id= : Specific test ID to debug}
                            {--fix : Attempt to fix issues found}
                            {--clear-locks : Clear all unique job locks}';

    protected $description = 'Debug queue processing issues and job creation';

    public function handle()
    {
        $this->info("==================================================");
        $this->info("Queue System Debug");
        $this->info("Time: " . now()->format('Y-m-d H:i:s'));
        $this->info("==================================================\n");

        // 1. Check basic configuration
        $this->checkConfiguration();
        
        // 2. Check database tables
        $this->checkDatabaseTables();
        
        // 3. Test job creation
        $this->testJobCreation();
        
        // 4. Check unique locks
        $this->checkUniqueLocks();
        
        // 5. Debug specific test if provided
        if ($testId = $this->option('test-id')) {
            $this->debugSpecificTest($testId);
        }
        
        // 6. Fix issues if requested
        if ($this->option('fix') || $this->option('clear-locks')) {
            $this->fixIssues();
        }
        
        return Command::SUCCESS;
    }
    
    protected function checkConfiguration()
    {
        $this->info("📋 CONFIGURATION CHECK");
        $this->info("------------------------");
        
        $queueDefault = config('queue.default');
        $queueConnection = env('QUEUE_CONNECTION');
        $queueDatabase = config('queue.connections.database.driver');
        
        $this->info("Queue Default: {$queueDefault}");
        $this->info("Queue Connection (env): {$queueConnection}");
        $this->info("Database Driver: {$queueDatabase}");
        
        if ($queueDefault !== 'database') {
            $this->error("⚠️ Queue is not using database driver!");
        } else {
            $this->info("✅ Queue configured to use database");
        }
        
        $this->info("");
    }
    
    protected function checkDatabaseTables()
    {
        $this->info("🗄️ DATABASE TABLES CHECK");
        $this->info("------------------------");
        
        // Check jobs table
        if (Schema::hasTable('jobs')) {
            $jobCount = DB::table('jobs')->count();
            $this->info("✅ 'jobs' table exists - {$jobCount} job(s) in queue");
            
            // Show jobs by queue
            $jobsByQueue = DB::table('jobs')
                ->select('queue', DB::raw('count(*) as count'))
                ->groupBy('queue')
                ->get();
            
            foreach ($jobsByQueue as $queue) {
                $this->info("   - {$queue->queue}: {$queue->count} jobs");
            }
        } else {
            $this->error("❌ 'jobs' table does not exist!");
        }
        
        // Check failed_jobs table
        if (Schema::hasTable('failed_jobs')) {
            $failedCount = DB::table('failed_jobs')->count();
            $this->info("✅ 'failed_jobs' table exists - {$failedCount} failed job(s)");
        } else {
            $this->error("❌ 'failed_jobs' table does not exist!");
        }
        
        $this->info("");
    }
    
    protected function testJobCreation()
    {
        $this->info("🧪 TESTING JOB CREATION");
        $this->info("------------------------");
        
        // Test 1: Simple TestJob
        $this->info("Test 1: Creating TestJob...");
        
        $beforeCount = DB::table('jobs')->count();
        
        try {
            if (class_exists(\App\Jobs\TestJob::class)) {
                \App\Jobs\TestJob::dispatch('Debug Test ' . now()->timestamp);
                $afterCount = DB::table('jobs')->count();
                
                if ($afterCount > $beforeCount) {
                    $this->info("✅ TestJob created successfully");
                    
                    // Clean up
                    DB::table('jobs')
                        ->where('payload', 'like', '%TestJob%')
                        ->delete();
                } else {
                    $this->error("❌ TestJob was dispatched but not queued");
                }
            } else {
                $this->warn("⚠️ TestJob class not found, skipping");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error creating TestJob: " . $e->getMessage());
        }
        
        // Test 2: ProcessEmailAddressJob
        $this->info("\nTest 2: Creating ProcessEmailAddressJob...");
        
        $testAccount = EmailAccount::where('is_active', true)->first();
        
        if ($testAccount) {
            $beforeCount = DB::table('jobs')->count();
            
            try {
                ProcessEmailAddressJob::dispatch($testAccount);
                $afterCount = DB::table('jobs')->count();
                
                if ($afterCount > $beforeCount) {
                    $this->info("✅ ProcessEmailAddressJob created successfully");
                    
                    // Clean up
                    DB::table('jobs')
                        ->where('queue', 'email-addresses')
                        ->where('payload', 'like', '%emailAccountId";i:' . $testAccount->id . '%')
                        ->delete();
                } else {
                    $this->warn("⚠️ ProcessEmailAddressJob dispatched but not queued");
                    $this->warn("   This might be due to ShouldBeUnique constraint");
                }
            } catch (\Exception $e) {
                $this->error("❌ Error creating ProcessEmailAddressJob: " . $e->getMessage());
            }
        } else {
            $this->warn("⚠️ No active email account found for testing");
        }
        
        $this->info("");
    }
    
    protected function checkUniqueLocks()
    {
        $this->info("🔒 UNIQUE JOB LOCKS CHECK");
        $this->info("------------------------");
        
        // Get all email accounts
        $accounts = EmailAccount::where('is_active', true)->get();
        $lockedCount = 0;
        $lockedAccounts = [];
        
        foreach ($accounts as $account) {
            $lockKey = 'laravel_unique_job:email-address-' . $account->id;
            
            if (Cache::has($lockKey)) {
                $lockedCount++;
                $lockedAccounts[] = $account;
                
                // Get TTL if possible
                try {
                    $ttl = Cache::store()->getStore()->ttl($lockKey);
                    $this->warn("🔒 Account {$account->email} (ID: {$account->id}) is LOCKED");
                    if ($ttl > 0) {
                        $this->warn("   TTL: {$ttl} seconds remaining");
                    }
                } catch (\Exception $e) {
                    $this->warn("🔒 Account {$account->email} (ID: {$account->id}) is LOCKED");
                }
            }
        }
        
        if ($lockedCount > 0) {
            $this->error("\n⚠️ Found {$lockedCount} locked account(s)");
            $this->info("These locks prevent new jobs from being created for these accounts.");
            $this->info("Locks expire after 5 minutes (300 seconds).");
            
            if (!$this->option('clear-locks')) {
                $this->info("\nUse --clear-locks to remove these locks");
            }
        } else {
            $this->info("✅ No locked accounts found");
        }
        
        $this->info("");
    }
    
    protected function debugSpecificTest($testId)
    {
        $this->info("🔍 DEBUGGING TEST: {$testId}");
        $this->info("------------------------");
        
        $test = Test::where('unique_id', $testId)->first();
        
        if (!$test) {
            $this->error("Test {$testId} not found");
            return;
        }
        
        $this->info("Test Status: {$test->status}");
        $this->info("Progress: {$test->received_emails}/{$test->expected_emails} emails");
        
        // Get test accounts
        $testAccounts = DB::table('test_email_accounts')
            ->join('email_accounts', 'test_email_accounts.email_account_id', '=', 'email_accounts.id')
            ->where('test_email_accounts.test_id', $test->id)
            ->select('email_accounts.*', 'test_email_accounts.email_received')
            ->get();
        
        $this->info("\nAccounts for this test:");
        
        foreach ($testAccounts as $account) {
            $status = $account->email_received ? "✅ Received" : "⏳ Waiting";
            $this->info("  {$account->email} - {$status}");
            
            // Check if locked
            $lockKey = 'laravel_unique_job:email-address-' . $account->id;
            if (Cache::has($lockKey)) {
                $this->warn("    🔒 LOCKED - Cannot create new job");
            }
            
            // Check if job exists
            $jobExists = DB::table('jobs')
                ->where('queue', 'email-addresses')
                ->where('payload', 'like', '%emailAccountId";i:' . $account->id . '%')
                ->exists();
            
            if ($jobExists) {
                $this->info("    📦 Job in queue");
            } elseif (!$account->email_received) {
                $this->warn("    ⚠️ No job in queue");
            }
        }
        
        $this->info("");
    }
    
    protected function fixIssues()
    {
        $this->info("🔧 FIXING ISSUES");
        $this->info("------------------------");
        
        if ($this->option('clear-locks')) {
            $this->info("Clearing all unique job locks...");
            
            $accounts = EmailAccount::where('is_active', true)->get();
            $clearedCount = 0;
            
            foreach ($accounts as $account) {
                $lockKey = 'laravel_unique_job:email-address-' . $account->id;
                
                if (Cache::has($lockKey)) {
                    Cache::forget($lockKey);
                    $clearedCount++;
                    $this->info("  Cleared lock for {$account->email}");
                }
            }
            
            if ($clearedCount > 0) {
                $this->info("✅ Cleared {$clearedCount} lock(s)");
                $this->info("\nYou can now run 'php artisan emails:process-optimized' to create new jobs");
            } else {
                $this->info("No locks to clear");
            }
        }
        
        if ($this->option('fix')) {
            // Additional fixes can be added here
            $this->info("\nChecking for other issues to fix...");
            
            // Fix stuck jobs
            $stuckJobs = DB::table('jobs')
                ->where('created_at', '<', now()->subHours(1)->timestamp)
                ->count();
            
            if ($stuckJobs > 0) {
                if ($this->confirm("Found {$stuckJobs} job(s) older than 1 hour. Delete them?")) {
                    DB::table('jobs')
                        ->where('created_at', '<', now()->subHours(1)->timestamp)
                        ->delete();
                    $this->info("✅ Deleted {$stuckJobs} stuck job(s)");
                }
            }
        }
        
        $this->info("\n==================================================");
        $this->info("Debug complete at " . now()->format('Y-m-d H:i:s'));
        $this->info("==================================================");
    }
}
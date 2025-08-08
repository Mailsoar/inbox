<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessEmailAddressJob;
use Illuminate\Support\Facades\Log;

class ProcessEmailAddresses extends Command
{
    protected $signature = 'emails:process-addresses 
                            {--timeout=50 : Stop accepting new jobs after this many seconds}
                            {--sleep=3 : Sleep time between checks when no jobs are available}';

    protected $description = 'Process email address jobs from the queue';

    public function handle()
    {
        $timeout = (int) $this->option('timeout');
        $sleep = (int) $this->option('sleep');
        $startTime = time();
        $jobsProcessed = 0;
        $iterations = 0;
        $actualJobsProcessed = 0;
        
        $this->info("==================================================");
        $this->info("Starting email address processing at " . now()->format('Y-m-d H:i:s'));
        $this->info("Configuration: timeout={$timeout}s, sleep={$sleep}s");
        $this->info("==================================================");
        
        Log::info('[ProcessEmailAddresses] ==================== STARTING ====================');
        Log::info('[ProcessEmailAddresses] Starting', [
            'time' => now()->format('Y-m-d H:i:s'),
            'timeout' => $timeout,
            'sleep' => $sleep,
            'pid' => getmypid()
        ]);
        
        while ((time() - $startTime) < $timeout) {
            $iterations++;
            
            // Check if there are jobs in the email-addresses queue
            $pendingJobs = \DB::table('jobs')
                ->where('queue', 'email-addresses')
                ->where('available_at', '<=', now()->timestamp)
                ->count();
            
            // Also check for delayed jobs
            $delayedJobs = \DB::table('jobs')
                ->where('queue', 'email-addresses')
                ->where('available_at', '>', now()->timestamp)
                ->count();
            
            $this->info("[" . now()->format('H:i:s') . "] Iteration {$iterations}:");
            $this->info("  Ready jobs: {$pendingJobs}");
            if ($delayedJobs > 0) {
                $this->info("  Delayed jobs: {$delayedJobs}");
            }
            
            Log::info('[ProcessEmailAddresses] Queue check', [
                'iteration' => $iterations,
                'time' => now()->format('Y-m-d H:i:s'),
                'ready_jobs' => $pendingJobs,
                'delayed_jobs' => $delayedJobs
            ]);
            
            if ($pendingJobs > 0) {
                $this->info("  📧 Processing {$pendingJobs} pending job(s)...");
                
                // Count jobs before processing
                $beforeCount = \DB::table('jobs')
                    ->where('queue', 'email-addresses')
                    ->count();
                
                // Get output to see what's happening
                $exitCode = \Artisan::call('queue:work', [
                    '--queue' => 'email-addresses',
                    '--stop-when-empty' => true,
                    '--max-jobs' => 10,
                    '--timeout' => 120,
                    '--memory' => 128,
                    '-vvv' => true,  // Maximum verbosity
                ]);
                
                $output = trim(\Artisan::output());
                
                // Count jobs after processing
                $afterCount = \DB::table('jobs')
                    ->where('queue', 'email-addresses')
                    ->count();
                
                $processedCount = $beforeCount - $afterCount;
                $actualJobsProcessed += $processedCount;
                
                if ($output) {
                    // Parse output to show meaningful info
                    $lines = explode("\n", $output);
                    foreach ($lines as $line) {
                        if (trim($line)) {
                            $this->info("    > " . trim($line));
                        }
                    }
                }
                
                if ($processedCount > 0) {
                    $this->info("  ✅ Processed {$processedCount} job(s) successfully");
                } else {
                    $this->warn("  ⚠️ No jobs were actually processed (exit code: {$exitCode})");
                }
                
                $jobsProcessed += min($pendingJobs, 10);
                
                Log::info('[ProcessEmailAddresses] Jobs processing attempt', [
                    'jobs_before' => $beforeCount,
                    'jobs_after' => $afterCount,
                    'processed' => $processedCount,
                    'exit_code' => $exitCode,
                    'total_processed' => $actualJobsProcessed,
                    'output_length' => strlen($output)
                ]);
            } else {
                $this->comment("  💤 No pending jobs, sleeping for {$sleep} seconds...");
                sleep($sleep);
            }
            
            // Check if we should stop
            if ((time() - $startTime) >= $timeout) {
                $this->info("Timeout reached after {$timeout} seconds");
                break;
            }
        }
        
        $elapsed = time() - $startTime;
        
        $this->info("==================================================");
        $this->info("Processing completed at " . now()->format('Y-m-d H:i:s'));
        $this->info("Summary:");
        $this->info("  - Duration: {$elapsed} seconds");
        $this->info("  - Iterations: {$iterations}");
        $this->info("  - Jobs attempted: {$jobsProcessed}");
        $this->info("  - Jobs actually processed: {$actualJobsProcessed}");
        
        // Check if there are still jobs in queue
        $remainingJobs = \DB::table('jobs')
            ->where('queue', 'email-addresses')
            ->count();
        
        if ($remainingJobs > 0) {
            $this->warn("  - ⚠️ Still {$remainingJobs} job(s) remaining in queue");
        }
        
        $this->info("==================================================");
        
        Log::info('[ProcessEmailAddresses] ==================== COMPLETED ====================');
        Log::info('[ProcessEmailAddresses] Summary', [
            'elapsed' => $elapsed,
            'iterations' => $iterations,
            'jobs_attempted' => $jobsProcessed,
            'jobs_processed' => $actualJobsProcessed,
            'remaining_jobs' => $remainingJobs,
            'pid' => getmypid()
        ]);
        
        return Command::SUCCESS;
    }
}
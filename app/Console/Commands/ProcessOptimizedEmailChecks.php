<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OptimizedEmailCheckService;
use Illuminate\Support\Facades\Log;

class ProcessOptimizedEmailChecks extends Command
{
    protected $signature = 'emails:process-optimized 
                            {--batch-size=5 : Number of accounts to process per batch}
                            {--timeout=300 : Maximum execution time in seconds}';

    protected $description = 'Process email checks with optimized batch processing and rate limiting';

    protected OptimizedEmailCheckService $emailService;

    public function __construct(OptimizedEmailCheckService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    public function handle()
    {
        $startTime = time();
        $timeout = (int) $this->option('timeout');
        $batchSize = (int) $this->option('batch-size');
        
        $this->info("Starting optimized email processing...");
        $this->info("Batch size: {$batchSize}, Timeout: {$timeout}s");
        
        try {
            // Pass the console instance to get output
            $this->emailService->processPendingChecks($this);
            
            $this->info("Processing completed!");
            
            Log::info('Optimized email processing completed');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error during processing: " . $e->getMessage());
            Log::error('Optimized email processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}
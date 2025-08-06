<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanOldMetricsCommand extends Command
{
    protected $signature = 'metrics:clean 
                            {--days=30 : Number of days to keep metrics}
                            {--dry-run : Run without actually deleting}';

    protected $description = 'Clean old email processing metrics from database';

    public function handle()
    {
        $daysToKeep = $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::now()->subDays($daysToKeep);
        
        $this->info("Cleaning metrics older than {$daysToKeep} days (before {$cutoffDate->format('Y-m-d H:i:s')})");
        
        // Count old metrics
        $oldMetricsCount = DB::table('email_processing_metrics')
            ->where('created_at', '<', $cutoffDate)
            ->count();
            
        if ($oldMetricsCount === 0) {
            $this->info("No old metrics to clean.");
            return 0;
        }
        
        $this->info("Found {$oldMetricsCount} old metrics to delete.");
        
        if ($dryRun) {
            $this->warn("Dry run mode - no data will be deleted.");
            
            // Show sample of what would be deleted
            $samples = DB::table('email_processing_metrics')
                ->where('created_at', '<', $cutoffDate)
                ->select(['id', 'run_id', 'created_at', 'status'])
                ->limit(10)
                ->get();
                
            $this->table(
                ['ID', 'Run ID', 'Created At', 'Status'],
                $samples->map(fn($m) => [
                    $m->id,
                    substr($m->run_id, 0, 8) . '...',
                    $m->created_at,
                    $m->status
                ])->toArray()
            );
            
            return 0;
        }
        
        // Delete old metrics
        $deleted = DB::table('email_processing_metrics')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
            
        $this->info("Successfully deleted {$deleted} old metrics.");
        
        // Also clean old failure records that have been resolved
        $oldFailures = DB::table('email_account_failures')
            ->where('updated_at', '<', $cutoffDate)
            ->where('failure_count', 0)
            ->delete();
            
        if ($oldFailures > 0) {
            $this->info("Also cleaned {$oldFailures} resolved failure records.");
        }
        
        return 0;
    }
}
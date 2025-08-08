<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Test;
use App\Models\TestResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixTestStatus extends Command
{
    protected $signature = 'test:fix-status 
                            {--test-id= : Specific test ID to fix}
                            {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Fix test status when all emails are received but status is still pending';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $testId = $this->option('test-id');
        
        if ($testId) {
            $tests = Test::where('unique_id', $testId)->get();
        } else {
            // Find all tests that might have this issue
            $tests = Test::whereIn('status', ['pending', 'in_progress'])
                ->where('created_at', '>', now()->subDays(7))
                ->get();
        }
        
        $fixedCount = 0;
        
        foreach ($tests as $test) {
            // Count actual received emails
            $actualReceived = DB::table('test_email_accounts')
                ->where('test_id', $test->id)
                ->where('email_received', true)
                ->count();
            
            // Count results in test_results table
            $resultsCount = TestResult::where('test_id', $test->id)->count();
            
            $this->info("Checking test {$test->unique_id}:");
            $this->info("  - Status: {$test->status}");
            $this->info("  - Expected: {$test->expected_emails}");
            $this->info("  - Received (field): {$test->received_emails}");
            $this->info("  - Actual received: {$actualReceived}");
            $this->info("  - Results count: {$resultsCount}");
            
            $needsFix = false;
            $updates = [];
            
            // Fix received_emails count if wrong
            if ($test->received_emails != $actualReceived) {
                $this->warn("  ⚠️ Received count mismatch!");
                $updates['received_emails'] = $actualReceived;
                $needsFix = true;
            }
            
            // Fix status if all emails received
            if ($actualReceived >= $test->expected_emails && $test->status !== 'completed') {
                $this->warn("  ⚠️ Status should be 'completed'!");
                $updates['status'] = 'completed';
                $needsFix = true;
            }
            
            // Fix status to timeout if timeout has passed and not all emails received
            if ($test->timeout_at < now() && $actualReceived < $test->expected_emails && $test->status !== 'timeout') {
                $this->warn("  ⚠️ Status should be 'timeout'!");
                $updates['status'] = 'timeout';
                $needsFix = true;
            }
            
            if ($needsFix) {
                if ($dryRun) {
                    $this->info("  🔧 Would update: " . json_encode($updates));
                } else {
                    $test->update($updates);
                    $this->info("  ✅ Fixed!");
                    
                    Log::info('[FixTestStatus] Test status corrected', [
                        'test_id' => $test->unique_id,
                        'updates' => $updates,
                        'actual_received' => $actualReceived,
                        'expected' => $test->expected_emails
                    ]);
                }
                $fixedCount++;
            } else {
                $this->info("  ✓ No issues found");
            }
            
            $this->info("");
        }
        
        if ($dryRun) {
            $this->info("Dry run complete. {$fixedCount} test(s) would be fixed.");
            $this->info("Run without --dry-run to apply fixes.");
        } else {
            $this->info("Fixed {$fixedCount} test(s).");
        }
        
        return Command::SUCCESS;
    }
}
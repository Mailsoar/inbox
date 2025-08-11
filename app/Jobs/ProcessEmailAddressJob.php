<?php

namespace App\Jobs;

use App\Models\Test;
use App\Models\TestResult;
use App\Models\EmailAccount;
use App\Services\EmailServiceFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProcessEmailAddressJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $emailAccount;
    public $emailAccountId; // Public property for easier search

    /**
     * The number of times the job may be attempted
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow
     */
    public $maxExceptions = 2;

    /**
     * The number of seconds the job can run before timing out
     */
    public $timeout = 120;

    /**
     * Create a new job instance
     */
    public function __construct(EmailAccount $emailAccount)
    {
        $this->emailAccount = $emailAccount;
        $this->emailAccountId = $emailAccount->id; // Store ID separately for searching
        
        // All email processing goes to the same queue for consistency
        $this->onQueue('email-addresses');
    }

    /**
     * The unique ID of the job
     */
    public function uniqueId(): string
    {
        return 'email-address-' . $this->emailAccount->id;
    }

    /**
     * The number of seconds after which the job's unique lock will be released
     */
    public function uniqueFor(): int
    {
        return 300; // 5 minutes
    }

    /**
     * Execute the job
     */
    public function handle()
    {
        Log::info('[ProcessEmailAddress] Starting email address processing', [
            'account' => $this->emailAccount->email,
            'provider' => $this->emailAccount->provider
        ]);

        try {
            // 1. Get all pending tests for this email address
            $pendingTests = $this->getPendingTests();
            
            if ($pendingTests->isEmpty()) {
                Log::info('[ProcessEmailAddress] No pending tests for address', [
                    'account' => $this->emailAccount->email
                ]);
                return; // Job naturally ends
            }

            Log::info('[ProcessEmailAddress] Found pending tests', [
                'account' => $this->emailAccount->email,
                'test_count' => $pendingTests->count(),
                'test_ids' => $pendingTests->pluck('unique_id')->toArray()
            ]);

            // 2. Check and mark timeouts
            $this->checkAndMarkTimeouts($pendingTests);

            // 3. Filter tests that need checking based on intervals
            $testsToCheck = $this->getTestsToCheck($pendingTests);
            
            if ($testsToCheck->isEmpty()) {
                Log::debug('[ProcessEmailAddress] No tests need checking right now', [
                    'account' => $this->emailAccount->email
                ]);
                
                // Don't re-dispatch - let the cron handle it
                return;
            }

            Log::info('[ProcessEmailAddress] Tests eligible for checking', [
                'account' => $this->emailAccount->email,
                'check_count' => $testsToCheck->count(),
                'test_ids' => $testsToCheck->pluck('unique_id')->toArray()
            ]);

            // 4. Search for all test IDs in one IMAP operation
            $foundEmails = $this->searchForAllTestIds($testsToCheck);

            // 5. Process found results
            $this->processFoundEmails($foundEmails, $testsToCheck);

            // Don't re-dispatch - let the cron handle the next run

        } catch (\Exception $e) {
            Log::error('[ProcessEmailAddress] Error processing address', [
                'account' => $this->emailAccount->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't re-dispatch on error - let the cron retry next time
            throw $e;
        }
    }

    /**
     * Get all pending tests for this email address
     */
    protected function getPendingTests()
    {
        // Only get active tests (not timed out)
        return Test::whereIn('status', ['pending', 'in_progress'])
            ->whereHas('emailAccounts', function($query) {
                $query->where('email_account_id', $this->emailAccount->id)
                      ->where('email_received', false);
            })
            ->where('timeout_at', '>', now()) // Not yet timed out
            ->get();
    }

    /**
     * Check and mark tests that have timed out
     */
    protected function checkAndMarkTimeouts($tests)
    {
        $timedOutTests = $tests->filter(function($test) {
            return $test->timeout_at <= now();
        });

        if ($timedOutTests->isNotEmpty()) {
            Log::info('[ProcessEmailAddress] Marking tests as timeout', [
                'account' => $this->emailAccount->email,
                'timeout_count' => $timedOutTests->count(),
                'test_ids' => $timedOutTests->pluck('unique_id')->toArray()
            ]);

            foreach ($timedOutTests as $test) {
                $test->update(['status' => 'timeout']);
            }
        }
    }

    /**
     * Get tests that need checking based on progressive intervals
     */
    protected function getTestsToCheck($tests)
    {
        $now = now();
        $testsToCheck = collect();

        foreach ($tests as $test) {
            if ($this->shouldCheckTest($test, $now)) {
                $testsToCheck->push($test);
            }
        }

        return $testsToCheck;
    }

    /**
     * Determine if a test should be checked based on progressive intervals
     */
    protected function shouldCheckTest($test, $now)
    {
        $ageMinutes = $now->diffInMinutes($test->created_at);
        
        // Get the last time we checked this test for this account
        $lastCheck = DB::table('test_email_accounts')
            ->where('test_id', $test->id)
            ->where('email_account_id', $this->emailAccount->id)
            ->value('last_checked_at');

        $lastCheckedAt = $lastCheck ? Carbon::parse($lastCheck) : $test->created_at;
        $minutesSinceLastCheck = $now->diffInMinutes($lastCheckedAt);

        // If never checked, always check
        if (!$lastCheck) {
            return true;
        }

        // Aggressive checking until timeout (30 minutes)
        if ($ageMinutes <= 10) {
            // 0-10 minutes: check every minute (emails usually arrive quickly)
            return $minutesSinceLastCheck >= 1;
        } elseif ($ageMinutes <= 20) {
            // 10-20 minutes: check every 2 minutes
            return $minutesSinceLastCheck >= 2;
        } elseif ($ageMinutes <= 30) {
            // 20-30 minutes: check every 3 minutes (last chance before timeout)
            return $minutesSinceLastCheck >= 3;
        } else {
            // Test will timeout at 30 minutes, no need to check
            return false;
        }
    }

    /**
     * Search for all test IDs in one IMAP operation
     */
    protected function searchForAllTestIds($tests)
    {
        try {
            $service = EmailServiceFactory::make($this->emailAccount);
            $foundEmails = [];
            
            foreach ($tests as $test) {
                Log::debug('[ProcessEmailAddress] Searching for test', [
                    'account' => $this->emailAccount->email,
                    'test_id' => $test->unique_id
                ]);

                // Pass the test object to the service for proper date filtering
                $results = $service->searchByUniqueId($test->unique_id, $test);
                
                if (!empty($results)) {
                    $foundEmails[$test->unique_id] = $results[0]; // Take first result
                    
                    Log::info('[ProcessEmailAddress] Found email for test', [
                        'account' => $this->emailAccount->email,
                        'test_id' => $test->unique_id,
                        'placement' => $results[0]['placement'] ?? 'unknown'
                    ]);
                }

                // Update last_checked_at regardless of result
                DB::table('test_email_accounts')
                    ->where('test_id', $test->id)
                    ->where('email_account_id', $this->emailAccount->id)
                    ->update(['last_checked_at' => now()]);
            }

            return $foundEmails;

        } catch (\Exception $e) {
            Log::error('[ProcessEmailAddress] Error during IMAP search', [
                'account' => $this->emailAccount->email,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Process found emails and update test results
     */
    protected function processFoundEmails($foundEmails, $tests)
    {
        foreach ($foundEmails as $testId => $emailData) {
            $test = $tests->firstWhere('unique_id', $testId);
            if (!$test) continue;

            try {
                // Parse authentication and size data
                $authData = $this->parseEmailAuthentication($emailData);
                $sizeBytes = $this->parseEmailSize($emailData);

                // Create test result
                $result = TestResult::create([
                    'test_id' => $test->id,
                    'email_account_id' => $this->emailAccount->id,
                    'message_id' => $emailData['message_id'] ?? null,
                    'from_email' => $emailData['from'] ?? $test->visitor_email,
                    'subject' => $emailData['subject'] ?? 'Test Email',
                    'placement' => $emailData['placement'] ?? 'inbox',
                    'folder_name' => $emailData['folder'] ?? 'INBOX',
                    'email_date' => isset($emailData['date']) ? 
                        Carbon::parse($emailData['date']) : now(),
                    'size_bytes' => $sizeBytes,
                    'raw_headers' => $emailData['headers'] ?? null,
                    'spf_result' => $authData['spf'],
                    'dkim_result' => $authData['dkim'],
                    'dmarc_result' => $authData['dmarc']
                ]);

                // Update test counts
                Log::info('[ProcessEmailAddress] Updating test progress', [
                    'test_id' => $test->unique_id,
                    'account' => $this->emailAccount->email,
                    'current_received' => $test->received_emails,
                    'will_be' => $test->received_emails + 1
                ]);
                
                $test->increment('received_emails');
                
                // Update pivot table
                $updated = DB::table('test_email_accounts')
                    ->where('test_id', $test->id)
                    ->where('email_account_id', $this->emailAccount->id)
                    ->update([
                        'email_received' => true,
                        'received_at' => now()
                    ]);
                    
                Log::info('[ProcessEmailAddress] Marked email as received in pivot', [
                    'test_id' => $test->unique_id,
                    'account' => $this->emailAccount->email,
                    'rows_updated' => $updated
                ]);

                // Reload test to get fresh count
                $test->refresh();
                
                // Check if test is complete
                if ($test->received_emails >= $test->expected_emails) {
                    $test->update(['status' => 'completed']);
                    
                    Log::info('[ProcessEmailAddress] ✅ TEST COMPLETED', [
                        'account' => $this->emailAccount->email,
                        'test_id' => $test->unique_id,
                        'received' => $test->received_emails,
                        'expected' => $test->expected_emails,
                        'status' => 'completed'
                    ]);
                } else {
                    Log::info('[ProcessEmailAddress] Test still in progress', [
                        'test_id' => $test->unique_id,
                        'received' => $test->received_emails,
                        'expected' => $test->expected_emails,
                        'remaining' => $test->expected_emails - $test->received_emails
                    ]);
                }

                Log::info('[ProcessEmailAddress] ✉️ Email processed successfully', [
                    'account' => $this->emailAccount->email,
                    'test_id' => $test->unique_id,
                    'placement' => $emailData['placement'] ?? 'unknown',
                    'folder' => $emailData['folder'] ?? 'INBOX',
                    'test_progress' => "{$test->received_emails}/{$test->expected_emails}"
                ]);

            } catch (\Exception $e) {
                Log::error('[ProcessEmailAddress] Error processing found email', [
                    'account' => $this->emailAccount->email,
                    'test_id' => $testId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    // REMOVED: Re-dispatch logic has been removed
    // The cron job (emails:process-optimized) runs every minute and creates new jobs as needed
    // This prevents delays in email checking and ensures consistent processing intervals

    /**
     * Parse authentication results from email headers
     */
    protected function parseEmailAuthentication($emailData): array
    {
        $authData = [
            'spf' => null,
            'dkim' => null,
            'dmarc' => null
        ];
        
        $headers = $emailData['headers'] ?? '';
        if (empty($headers)) {
            return $authData;
        }
        
        // Parse SPF - DB accepts: pass,fail,softfail,neutral,none,temperror,permerror
        if (preg_match('/Received-SPF:\s*(\w+)/i', $headers, $matches)) {
            $spfValue = strtolower($matches[1]);
            // SPF column accepts temperror, so we can use it directly
            if (in_array($spfValue, ['pass', 'fail', 'softfail', 'neutral', 'none', 'temperror', 'permerror'])) {
                $authData['spf'] = $spfValue;
            }
        } elseif (preg_match('/spf=(\w+)/i', $headers, $matches)) {
            $spfValue = strtolower($matches[1]);
            if (in_array($spfValue, ['pass', 'fail', 'softfail', 'neutral', 'none', 'temperror', 'permerror'])) {
                $authData['spf'] = $spfValue;
            }
        }
        
        // Parse DKIM - DB only accepts: pass,fail,none
        if (preg_match('/dkim=(\w+)/i', $headers, $matches)) {
            $dkimValue = strtolower($matches[1]);
            if (in_array($dkimValue, ['pass', 'fail', 'none'])) {
                $authData['dkim'] = $dkimValue;
            } elseif (in_array($dkimValue, ['timeout', 'temperror', 'permerror'])) {
                // Map timeout/temperror/permerror to 'fail' since DB doesn't support these values
                $authData['dkim'] = 'fail';
            }
        } elseif (preg_match('/DKIM-Signature:/i', $headers)) {
            if (preg_match('/dkim=none/i', $headers)) {
                $authData['dkim'] = 'none';
            } else {
                $authData['dkim'] = 'pass';
            }
        }
        
        // Parse DMARC - DB only accepts: pass,fail,none
        if (preg_match('/dmarc=(\w+)/i', $headers, $matches)) {
            $dmarcValue = strtolower($matches[1]);
            if (in_array($dmarcValue, ['pass', 'fail', 'none'])) {
                $authData['dmarc'] = $dmarcValue;
            } elseif (in_array($dmarcValue, ['temperror', 'permerror', 'bestguesspass'])) {
                // Map temperror/permerror to 'fail' since DB doesn't support these values
                $authData['dmarc'] = 'fail';
            }
        }
        
        // Extract from Authentication-Results header (override above if found)
        if (preg_match('/Authentication-Results:[^\r\n]*([^\r\n]+)/i', $headers, $matches)) {
            $authResults = $matches[1];
            
            if (preg_match('/spf=(\w+)/i', $authResults, $spfMatch)) {
                $spfValue = strtolower($spfMatch[1]);
                if (in_array($spfValue, ['pass', 'fail', 'softfail', 'neutral', 'none', 'temperror', 'permerror'])) {
                    $authData['spf'] = $spfValue;
                }
            }
            if (preg_match('/dkim=(\w+)/i', $authResults, $dkimMatch)) {
                $dkimValue = strtolower($dkimMatch[1]);
                if (in_array($dkimValue, ['pass', 'fail', 'none'])) {
                    $authData['dkim'] = $dkimValue;
                } elseif (in_array($dkimValue, ['timeout', 'temperror', 'permerror'])) {
                    $authData['dkim'] = 'fail';
                }
            }
            if (preg_match('/dmarc=(\w+)/i', $authResults, $dmarcMatch)) {
                $dmarcValue = strtolower($dmarcMatch[1]);
                if (in_array($dmarcValue, ['pass', 'fail', 'none'])) {
                    $authData['dmarc'] = $dmarcValue;
                } elseif (in_array($dmarcValue, ['temperror', 'permerror', 'bestguesspass'])) {
                    $authData['dmarc'] = 'fail';
                }
            }
        }
        
        return $authData;
    }

    /**
     * Parse email size from data
     */
    protected function parseEmailSize($emailData): int
    {
        if (isset($emailData['size']) && is_numeric($emailData['size'])) {
            return (int) $emailData['size'];
        }
        
        if (isset($emailData['size_bytes']) && is_numeric($emailData['size_bytes'])) {
            return (int) $emailData['size_bytes'];
        }
        
        $estimatedSize = 0;
        if (!empty($emailData['headers'])) {
            $estimatedSize += strlen($emailData['headers']);
        }
        if (!empty($emailData['body'])) {
            $estimatedSize += strlen($emailData['body']);
        }
        
        return $estimatedSize;
    }
}
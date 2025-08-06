<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailProvider;
use App\Models\Test;
use App\Models\TestResult;
use App\Services\EmailServiceFactory;
use App\Jobs\ProcessEmailAddressJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OptimizedEmailCheckService
{
    protected $imapConnections = [];
    protected $emailServices = [];
    protected $console = null;
    
    /**
     * Process pending email checks with optimized batching
     */
    public function processPendingChecks($console = null)
    {
        $this->console = $console;
        
        $this->output("========================================");
        $this->output("Starting batch email check process", 'info');
        
        Log::info('[OptimizedEmailCheck] ========================================');
        Log::info('[OptimizedEmailCheck] Starting batch email check process at ' . now()->format('Y-m-d H:i:s'));
        
        // First, check and mark any tests that have timed out
        $this->checkAndMarkTimeouts();
        
        // Get active tests that need checking
        $this->output("Getting active tests...");
        $activeTests = $this->getActiveTests();
        
        $this->output("Found " . $activeTests->count() . " active tests");
        if ($activeTests->isNotEmpty()) {
            $this->output("Test IDs: " . implode(', ', $activeTests->pluck('unique_id')->toArray()));
        }
        
        Log::info('[OptimizedEmailCheck] Found active tests', [
            'total_tests' => $activeTests->count(),
            'test_ids' => $activeTests->pluck('unique_id')->toArray()
        ]);
        
        if ($activeTests->isEmpty()) {
            $this->output("No active tests to process", 'comment');
            Log::info('[OptimizedEmailCheck] No active tests to process');
            return ['dispatched' => 0, 'addresses' => 0, 'errors' => 0];
        }
        
        // NEW APPROACH: Dispatch ProcessEmailAddressJob for each unique email address
        $this->output("Dispatching address-based jobs...");
        $stats = $this->dispatchAddressJobs($activeTests);
        
        $this->output("Process completed - Jobs dispatched: {$stats['dispatched']}, Addresses: {$stats['addresses']}", 'info');
        $this->output("========================================");
        
        Log::info('[OptimizedEmailCheck] Address-based jobs dispatched', $stats);
        
        return $stats;
    }
    
    /**
     * Get active tests that need email checking
     */
    protected function getActiveTests()
    {
        // Only get tests that are actively being processed (not timed out)
        return Test::whereIn('status', ['pending', 'in_progress'])
            ->where('timeout_at', '>', now()) // Not yet timed out
            ->with(['emailAccounts' => function($query) {
                $query->where('is_active', true);
            }])
            ->get();
    }
    
    /**
     * Group tests by email account for batch processing
     */
    protected function groupByEmailAccount($tests)
    {
        $accountBatches = [];
        
        foreach ($tests as $test) {
            // The test ID should be in the subject or body
            $testId = $test->unique_id;
            
            Log::info('[OptimizedEmailCheck] Processing test', [
                'test_id' => $testId,
                'expected_emails' => $test->expected_emails,
                'received_emails' => $test->received_emails,
                'status' => $test->status,
                'timeout_at' => $test->timeout_at,
                'visitor_email' => $test->visitor_email
            ]);
            
            foreach ($test->emailAccounts as $account) {
                // Check if email already received for this account
                $alreadyReceived = TestResult::where('test_id', $test->id)
                    ->where('email_account_id', $account->id)
                    ->exists();
                
                if ($alreadyReceived) {
                    Log::debug('[OptimizedEmailCheck] Email already received', [
                        'test_id' => $testId,
                        'account_id' => $account->id,
                        'email' => $account->email
                    ]);
                    continue;
                }
                
                if (!isset($accountBatches[$account->id])) {
                    $accountBatches[$account->id] = [
                        'account' => $account,
                        'checks' => []
                    ];
                }
                
                $accountBatches[$account->id]['checks'][] = [
                    'test' => $test,
                    'test_id' => $testId
                ];
                
                Log::debug('[OptimizedEmailCheck] Added to batch', [
                    'account_id' => $account->id,
                    'email' => $account->email,
                    'test_id' => $testId
                ]);
            }
        }
        
        return $accountBatches;
    }
    
    /**
     * Process a batch of emails for one account
     */
    protected function processAccountBatch($accountId, $batchData)
    {
        $account = $batchData['account'];
        $checks = $batchData['checks'];
        $foundCount = 0;
        
        $this->output("  - Provider: {$account->provider}, Checks: " . count($checks));
        
        Log::info('[OptimizedEmailCheck] Processing account batch', [
            'account_id' => $accountId,
            'email' => $account->email,
            'provider' => $account->provider,
            'checks_count' => count($checks)
        ]);
        
        // Check rate limits
        if (!$this->checkRateLimits($account)) {
            $this->output("  - SKIPPED: Rate limited", 'comment');
            Log::warning('[OptimizedEmailCheck] Account rate limited', [
                'account_id' => $accountId,
                'email' => $account->email
            ]);
            return ['found' => 0];
        }
        
        try {
            $this->output("  - Getting service connection...");
            // Get or create email service
            $service = $this->getImapConnection($account);
            
            if (!$service) {
                $this->output("  - ERROR: Failed to get service", 'error');
                Log::error('[OptimizedEmailCheck] Failed to get service', [
                    'account_id' => $accountId,
                    'email' => $account->email
                ]);
                return ['found' => 0];
            }
            
            // Extract test IDs and visitor emails to search
            $testIds = array_column($checks, 'test_id');
            $testsMap = [];
            foreach ($checks as $check) {
                $testsMap[$check['test_id']] = $check['test'];
            }
            
            $this->output("  - Searching for test IDs: " . implode(', ', $testIds));
            
            Log::info('[OptimizedEmailCheck] Searching for test emails', [
                'account' => $account->email,
                'test_ids' => $testIds,
                'looking_for' => 'Test IDs in subject/body'
            ]);
            
            // Batch check all test IDs
            $foundEmails = $this->batchCheckEmails($service, $testsMap, $account);
            
            $this->output("  - Search completed: " . count($foundEmails) . " found");
            
            Log::info('[OptimizedEmailCheck] Search completed', [
                'account' => $account->email,
                'found_count' => count($foundEmails),
                'found_test_ids' => array_keys($foundEmails)
            ]);
            
            // Process results
            foreach ($checks as $check) {
                $testId = $check['test_id'];
                $test = $check['test'];
                
                if (isset($foundEmails[$testId])) {
                    $foundCount++;
                    $placement = $foundEmails[$testId]['placement'] ?? 'unknown';
                    $this->output("  ✓ FOUND: Test {$testId} - {$placement}", 'info');
                    
                    Log::info('[OptimizedEmailCheck] Email FOUND!', [
                        'test_id' => $testId,
                        'account' => $account->email,
                        'placement' => $foundEmails[$testId]['placement'] ?? 'unknown',
                        'subject' => $foundEmails[$testId]['subject'] ?? 'no subject'
                    ]);
                    
                    $this->processFoundEmail($test, $account, $foundEmails[$testId]);
                } else {
                    $this->output("  ✗ Not found: Test {$testId}", 'comment');
                    Log::debug('[OptimizedEmailCheck] Email not found yet', [
                        'test_id' => $testId,
                        'account' => $account->email
                    ]);
                }
            }
            
            return ['found' => $foundCount];
            
        } catch (\Exception $e) {
            $this->output("  - ERROR: " . $e->getMessage(), 'error');
            
            Log::error('[OptimizedEmailCheck] Error processing account', [
                'account_id' => $accountId,
                'email' => $account->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->handleConnectionError($account, $e);
            throw $e;
        }
    }
    
    /**
     * Check rate limits for account
     */
    protected function checkRateLimits($account)
    {
        // Check backoff
        if ($account->backoff_until && $account->backoff_until > now()) {
            Log::debug('[OptimizedEmailCheck] Account in backoff', [
                'email' => $account->email,
                'backoff_until' => $account->backoff_until
            ]);
            return false;
        }
        
        $provider = $account->emailProvider;
        if (!$provider) {
            return true; // No limits if no provider
        }
        
        // Check hourly connection limit
        $hourStart = now()->startOfHour();
        $connectionsThisHour = DB::table('email_connection_tracking')
            ->where('email_account_id', $account->id)
            ->where('hour_started_at', $hourStart)
            ->value('connections_count') ?? 0;
        
        if ($connectionsThisHour >= $provider->max_connections_per_hour) {
            Log::debug('[OptimizedEmailCheck] Hourly rate limit reached', [
                'email' => $account->email,
                'connections_this_hour' => $connectionsThisHour,
                'limit' => $provider->max_connections_per_hour
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Update connection tracking
     */
    protected function updateConnectionTracking($accountId)
    {
        $hourStart = now()->startOfHour();
        
        DB::table('email_connection_tracking')->updateOrInsert(
            [
                'email_account_id' => $accountId,
                'hour_started_at' => $hourStart
            ],
            [
                'connections_count' => DB::raw('IFNULL(connections_count, 0) + 1'),
                'last_connection_at' => now(),
                'updated_at' => now()
            ]
        );
        
        Log::debug('[OptimizedEmailCheck] Updated connection tracking', [
            'account_id' => $accountId,
            'hour_started_at' => $hourStart
        ]);
    }
    
    /**
     * Get or create email service/IMAP connection
     */
    protected function getImapConnection($account)
    {
        $key = $account->id;
        
        Log::debug('[OptimizedEmailCheck] Getting connection for account', [
            'account_id' => $account->id,
            'email' => $account->email,
            'provider' => $account->provider,
            'auth_type' => $account->auth_type
        ]);
        
        // Return existing service if available
        if (isset($this->emailServices[$key])) {
            try {
                Log::debug('[OptimizedEmailCheck] Reusing existing service', [
                    'account' => $account->email
                ]);
                return $this->emailServices[$key];
            } catch (\Exception $e) {
                // Service might be disconnected, recreate
                Log::debug('[OptimizedEmailCheck] Existing service failed, creating new one', [
                    'account' => $account->email,
                    'error' => $e->getMessage()
                ]);
                unset($this->emailServices[$key]);
            }
        }
        
        // Update connection tracking
        $this->updateConnectionTracking($account->id);
        
        // Create new service
        try {
            Log::info('[OptimizedEmailCheck] Creating new email service', [
                'account' => $account->email,
                'provider' => $account->provider,
                'auth_type' => $account->auth_type
            ]);
            
            // Use EmailServiceFactory to create the appropriate service
            $service = EmailServiceFactory::make($account);
            
            if (!$service) {
                Log::error('[OptimizedEmailCheck] Failed to create email service', [
                    'account' => $account->email
                ]);
                return null;
            }
            
            // Store for reuse (services handle their own connection internally)
            $this->emailServices[$key] = $service;
            
            Log::info('[OptimizedEmailCheck] Service created successfully', [
                'account' => $account->email,
                'service_class' => get_class($service)
            ]);
            
            return $service;
            
        } catch (\Exception $e) {
            Log::error('[OptimizedEmailCheck] Service creation/connection failed', [
                'account' => $account->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Batch check emails
     */
    protected function batchCheckEmails($service, $testsMap, $account)
    {
        $found = [];
        
        Log::info('[OptimizedEmailCheck] Starting batch check', [
            'account' => $account->email,
            'tests_count' => count($testsMap),
            'service_type' => get_class($service)
        ]);
        
        // Pour chaque test, chercher l'email correspondant
        foreach ($testsMap as $testId => $test) {
            try {
                Log::debug('[OptimizedEmailCheck] Searching for test email', [
                    'test_id' => $testId,
                    'visitor_email' => $test->visitor_email,
                    'account' => $account->email
                ]);
                
                // Utiliser la méthode searchByUniqueId du service
                // Cette méthode cherche le test ID dans le sujet ou le body
                $results = $service->searchByUniqueId($testId);
                
                Log::debug('[OptimizedEmailCheck] Search results for test', [
                    'test_id' => $testId,
                    'found' => !empty($results),
                    'results_count' => count($results)
                ]);
                
                if (!empty($results)) {
                    // Prendre le premier résultat trouvé
                    $emailData = $results[0];
                    
                    Log::info('[OptimizedEmailCheck] ✓ Email FOUND for test!', [
                        'test_id' => $testId,
                        'account' => $account->email,
                        'subject' => $emailData['subject'] ?? 'no subject',
                        'placement' => $emailData['placement'] ?? 'unknown',
                        'folder' => $emailData['folder'] ?? 'unknown'
                    ]);
                    
                    $found[$testId] = $emailData;
                } else {
                    Log::debug('[OptimizedEmailCheck] No email found yet for test', [
                        'test_id' => $testId,
                        'account' => $account->email
                    ]);
                }
                
            } catch (\Exception $e) {
                Log::warning('[OptimizedEmailCheck] Error searching for test', [
                    'test_id' => $testId,
                    'account' => $account->email,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        Log::info('[OptimizedEmailCheck] Batch check completed', [
            'account' => $account->email,
            'total_searched' => count($testsMap),
            'total_found' => count($found),
            'found_test_ids' => array_keys($found)
        ]);
        
        return $found;
    }
    
    /**
     * Process found email
     */
    protected function processFoundEmail($test, $account, $emailData)
    {
        Log::info('[OptimizedEmailCheck] Processing found email', [
            'test_id' => $test->unique_id,
            'account' => $account->email,
            'placement' => $emailData['placement'] ?? 'unknown',
            'subject' => $emailData['subject'] ?? 'no subject'
        ]);
        
        // Check if already exists
        $exists = TestResult::where('test_id', $test->id)
            ->where('email_account_id', $account->id)
            ->exists();
        
        if ($exists) {
            Log::debug('[OptimizedEmailCheck] Test result already exists', [
                'test_id' => $test->unique_id,
                'account_id' => $account->id
            ]);
            return;
        }
        
        // Parse authentication and size data
        $authData = $this->parseEmailAuthentication($emailData);
        $sizeBytes = $this->parseEmailSize($emailData);
        
        // Create test result
        $result = TestResult::create([
            'test_id' => $test->id,
            'email_account_id' => $account->id,
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
        $test->increment('received_emails');
        
        // Update pivot table
        DB::table('test_email_accounts')
            ->where('test_id', $test->id)
            ->where('email_account_id', $account->id)
            ->update([
                'email_received' => true,
                'received_at' => now()
            ]);
        
        // Check if test is complete
        if ($test->received_emails >= $test->expected_emails) {
            $test->update(['status' => 'completed']);
            Log::info('[OptimizedEmailCheck] Test completed!', [
                'test_id' => $test->unique_id,
                'received' => $test->received_emails,
                'expected' => $test->expected_emails
            ]);
        }
        
        Log::info('[OptimizedEmailCheck] Test result created', [
            'test_id' => $test->unique_id,
            'account' => $account->email,
            'placement' => $result->placement
        ]);
    }
    
    /**
     * Handle connection error
     */
    protected function handleConnectionError($account, $e)
    {
        $error = $e->getMessage();
        
        Log::error('[OptimizedEmailCheck] Connection error', [
            'account_id' => $account->id,
            'email' => $account->email,
            'error' => $error
        ]);
        
        // Check if it's a rate limit error
        if (stripos($error, 'rate limit') !== false || stripos($error, '[LIMIT]') !== false) {
            $provider = $account->emailProvider;
            $backoffMinutes = $provider ? $provider->connection_backoff_minutes : 30;
            
            $account->update([
                'backoff_until' => now()->addMinutes($backoffMinutes),
                'connection_error' => $error
            ]);
            
            Log::warning('[OptimizedEmailCheck] Account backed off due to rate limit', [
                'account' => $account->email,
                'backoff_until' => $account->backoff_until
            ]);
        } else {
            // Other error - just log it
            $account->update([
                'connection_error' => $error,
                'connection_status' => 'failed'
            ]);
        }
    }
    
    /**
     * Close all connections
     */
    protected function closeAllConnections()
    {
        Log::debug('[OptimizedEmailCheck] Closing all services', [
            'total_services' => count($this->emailServices)
        ]);
        
        foreach ($this->emailServices as $key => $service) {
            try {
                // Services handle their own disconnection in destructor
                // Just clear the reference
                unset($this->emailServices[$key]);
                Log::debug('[OptimizedEmailCheck] Service reference cleared', [
                    'key' => $key
                ]);
            } catch (\Exception $e) {
                // Ignore close errors
                Log::debug('[OptimizedEmailCheck] Error clearing service', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->emailServices = [];
    }
    
    /**
     * Output to console if available, otherwise use echo
     */
    protected function output($message, $type = 'line')
    {
        $timestamp = "[" . now()->format('H:i:s') . "] ";
        
        if ($this->console) {
            switch($type) {
                case 'info':
                    $this->console->info($timestamp . $message);
                    break;
                case 'error':
                    $this->console->error($timestamp . $message);
                    break;
                case 'comment':
                    $this->console->comment($timestamp . $message);
                    break;
                case 'warning':
                    $this->console->warn($timestamp . $message);
                    break;
                default:
                    $this->console->line($timestamp . $message);
            }
        } else {
            echo $timestamp . $message . "\n";
        }
    }
    
    /**
     * Check and mark tests that have timed out
     */
    protected function checkAndMarkTimeouts()
    {
        $timedOutTests = Test::whereIn('status', ['pending', 'in_progress'])
            ->where('timeout_at', '<=', now())
            ->get();
            
        if ($timedOutTests->isNotEmpty()) {
            $this->output("Marking " . $timedOutTests->count() . " tests as timeout", 'comment');
            
            foreach ($timedOutTests as $test) {
                $test->update(['status' => 'timeout']);
                
                Log::info('[OptimizedEmailCheck] Test marked as timeout', [
                    'test_id' => $test->unique_id,
                    'timeout_at' => $test->timeout_at,
                    'received' => $test->received_emails,
                    'expected' => $test->expected_emails
                ]);
            }
        }
    }
    
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
        
        // Parse SPF
        if (preg_match('/Received-SPF:\s*(\w+)/i', $headers, $matches)) {
            $authData['spf'] = strtolower($matches[1]);
        } elseif (preg_match('/spf=(\w+)/i', $headers, $matches)) {
            $authData['spf'] = strtolower($matches[1]);
        }
        
        // Parse DKIM
        if (preg_match('/dkim=(\w+)/i', $headers, $matches)) {
            $authData['dkim'] = strtolower($matches[1]);
        } elseif (preg_match('/DKIM-Signature:/i', $headers)) {
            // If we have a signature but no explicit result, check for verification
            if (preg_match('/dkim=none/i', $headers)) {
                $authData['dkim'] = 'none';
            } else {
                $authData['dkim'] = 'pass'; // Assume pass if signature present
            }
        }
        
        // Parse DMARC
        if (preg_match('/dmarc=(\w+)/i', $headers, $matches)) {
            $authData['dmarc'] = strtolower($matches[1]);
        }
        
        // Extract from Authentication-Results header
        if (preg_match('/Authentication-Results:[^\r\n]*([^\r\n]+)/i', $headers, $matches)) {
            $authResults = $matches[1];
            
            // More detailed parsing
            if (preg_match('/spf=(\w+)/i', $authResults, $spfMatch)) {
                $authData['spf'] = strtolower($spfMatch[1]);
            }
            if (preg_match('/dkim=(\w+)/i', $authResults, $dkimMatch)) {
                $authData['dkim'] = strtolower($dkimMatch[1]);
            }
            if (preg_match('/dmarc=(\w+)/i', $authResults, $dmarcMatch)) {
                $authData['dmarc'] = strtolower($dmarcMatch[1]);
            }
        }
        
        Log::debug('[OptimizedEmailCheck] Parsed authentication', [
            'spf' => $authData['spf'],
            'dkim' => $authData['dkim'],
            'dmarc' => $authData['dmarc']
        ]);
        
        return $authData;
    }
    
    /**
     * Parse email size from data
     */
    protected function parseEmailSize($emailData): int
    {
        // Try different size sources
        if (isset($emailData['size']) && is_numeric($emailData['size'])) {
            return (int) $emailData['size'];
        }
        
        if (isset($emailData['size_bytes']) && is_numeric($emailData['size_bytes'])) {
            return (int) $emailData['size_bytes'];
        }
        
        // Calculate from headers and body if available
        $estimatedSize = 0;
        if (!empty($emailData['headers'])) {
            $estimatedSize += strlen($emailData['headers']);
        }
        if (!empty($emailData['body'])) {
            $estimatedSize += strlen($emailData['body']);
        }
        
        Log::debug('[OptimizedEmailCheck] Parsed email size', [
            'size' => $estimatedSize,
            'has_headers' => !empty($emailData['headers']),
            'has_body' => !empty($emailData['body'])
        ]);
        
        return $estimatedSize;
    }
    
    /**
     * Dispatch ProcessEmailAddressJob for each unique email address with pending tests
     */
    protected function dispatchAddressJobs($activeTests): array
    {
        // Global lock to prevent concurrent job creation from multiple cron runs
        $globalLock = \Illuminate\Support\Facades\Cache::lock('global-email-job-dispatch', 30);
        
        if (!$globalLock->get()) {
            $this->output("  ⚠️ Another process is already dispatching jobs, skipping", 'comment');
            Log::warning('[OptimizedEmailCheck] Could not acquire global lock for job dispatch');
            return ['dispatched' => 0, 'addresses' => 0, 'errors' => 0];
        }
        
        try {
            // Get unique email addresses that have pending tests
            $emailAccountIds = [];
            
            foreach ($activeTests as $test) {
                foreach ($test->emailAccounts as $account) {
                    // Only include accounts that haven't received the email yet
                    $pivotData = $account->pivot;
                    if (!$pivotData->email_received) {
                        $emailAccountIds[] = $account->id;
                    }
                }
            }
            
            $uniqueAccountIds = array_unique($emailAccountIds);
            $emailAccounts = EmailAccount::whereIn('id', $uniqueAccountIds)->get();
            
            $dispatched = 0;
            $errors = 0;
        
        foreach ($emailAccounts as $account) {
            try {
                // Use cache lock to prevent duplicate job dispatch
                $lockKey = 'job-dispatch:email-address:' . $account->id;
                $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10); // 10 second lock
                
                if ($lock->get()) {
                    try {
                        // Double-check if job exists in queue
                        // Search for the emailAccountId property in the serialized job (PHP serialization format)
                        $existingJob = DB::table('jobs')
                            ->where('queue', 'email-addresses')
                            ->where('payload', 'like', '%emailAccountId";i:' . $account->id . ';%')
                            ->first();
                        
                        if (!$existingJob) {
                            // Also check with a more robust method
                            $existingJob = DB::table('jobs')
                                ->where('queue', 'email-addresses')
                                ->get()
                                ->filter(function ($job) use ($account) {
                                    $payload = json_decode($job->payload, true);
                                    if (!isset($payload['data']['command'])) {
                                        return false;
                                    }
                                    try {
                                        $command = unserialize($payload['data']['command']);
                                        $reflection = new \ReflectionClass($command);
                                        if ($reflection->hasProperty('emailAccount')) {
                                            $property = $reflection->getProperty('emailAccount');
                                            $property->setAccessible(true);
                                            $emailAccount = $property->getValue($command);
                                            return $emailAccount->id === $account->id;
                                        }
                                    } catch (\Exception $e) {
                                        return false;
                                    }
                                    return false;
                                })
                                ->first();
                        }
                        
                        if (!$existingJob) {
                            ProcessEmailAddressJob::dispatch($account);
                            $dispatched++;
                            
                            $this->output("  ✓ Job dispatched for {$account->email} ({$account->provider})", 'info');
                            
                            Log::info('[OptimizedEmailCheck] Address job dispatched', [
                                'account' => $account->email,
                                'provider' => $account->provider
                            ]);
                        } else {
                            $this->output("  ⏳ Job already queued for {$account->email}", 'comment');
                            
                            Log::debug('[OptimizedEmailCheck] Job already queued for address', [
                                'account' => $account->email,
                                'job_id' => $existingJob->id
                            ]);
                        }
                    } finally {
                        $lock->release();
                    }
                } else {
                    $this->output("  ⏳ Could not acquire lock for {$account->email}, skipping", 'comment');
                    Log::debug('[OptimizedEmailCheck] Could not acquire lock for address', [
                        'account' => $account->email
                    ]);
                }
                
            } catch (\Exception $e) {
                $errors++;
                $this->output("  ✗ Error dispatching job for {$account->email}: " . $e->getMessage(), 'error');
                
                Log::error('[OptimizedEmailCheck] Error dispatching address job', [
                    'account' => $account->email,
                    'error' => $e->getMessage()
                ]);
            }
        }
            
            return [
                'dispatched' => $dispatched,
                'addresses' => $emailAccounts->count(),
                'errors' => $errors
            ];
        } finally {
            $globalLock->release();
        }
    }
}
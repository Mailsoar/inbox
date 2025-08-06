<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\PlacementTest;
use App\Models\TestResult;
use App\Models\TestAuthentication;
use Illuminate\Support\Facades\Log;

class TestResultProcessor
{
    /**
     * Process an email result
     */
    public function processEmailResult(PlacementTest $test, EmailAccount $emailAccount, array $emailData): void
    {
        try {
            // Check if we already have this result
            $existingResult = TestResult::where('test_id', $test->id)
                ->where('provider', $emailAccount->provider)
                ->where('email', $emailAccount->email)
                ->first();

            if ($existingResult) {
                Log::info("Result already exists for test {$test->unique_id} and email {$emailAccount->email}");
                return;
            }

            // Create the test result
            $result = TestResult::create([
                'test_id' => $test->id,
                'provider' => $emailAccount->provider,
                'email' => $emailAccount->email,
                'placement' => $emailData['placement'],
                'received_at' => $emailData['date'] ?? now(),
                'headers' => $emailData['headers'] ?? '',
                'message_id' => $emailData['message_id'] ?? null,
                'subject' => $emailData['subject'] ?? '',
                'from_address' => $emailData['from'] ?? '',
                'folder' => $emailData['folder'] ?? '',
                'body_preview' => $emailData['body'] ?? '',
            ]);

            // Parse authentication headers
            if (!empty($emailData['headers'])) {
                $this->parseAuthenticationHeaders($result, $emailData['headers']);
            }

            // Update test status
            $this->updateTestStatus($test);

            Log::info("Email result processed successfully", [
                'test_id' => $test->unique_id,
                'email' => $emailAccount->email,
                'placement' => $emailData['placement']
            ]);

        } catch (\Exception $e) {
            Log::error("Error processing email result", [
                'test_id' => $test->unique_id,
                'email' => $emailAccount->email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Parse authentication headers (SPF, DKIM, DMARC)
     */
    protected function parseAuthenticationHeaders(TestResult $result, string $headers): void
    {
        $authData = [
            'test_result_id' => $result->id,
            'spf_result' => 'none',
            'dkim_result' => 'none',
            'dmarc_result' => 'none',
            'raw_headers' => $headers
        ];

        // Parse SPF
        if (preg_match('/Received-SPF:\s*(\w+)/i', $headers, $matches)) {
            $authData['spf_result'] = strtolower($matches[1]);
        }
        if (preg_match('/spf=(\w+)/i', $headers, $matches)) {
            $authData['spf_result'] = strtolower($matches[1]);
        }

        // Parse DKIM
        if (preg_match('/dkim=(\w+)/i', $headers, $matches)) {
            $authData['dkim_result'] = strtolower($matches[1]);
        }
        if (preg_match('/DKIM-Signature:/i', $headers)) {
            // If we have a signature but no explicit result, assume it needs verification
            if ($authData['dkim_result'] === 'none') {
                $authData['dkim_result'] = 'signed';
            }
        }

        // Parse DMARC
        if (preg_match('/dmarc=(\w+)/i', $headers, $matches)) {
            $authData['dmarc_result'] = strtolower($matches[1]);
        }

        // Extract authentication results header
        if (preg_match('/Authentication-Results:([^;]+(?:;[^;]+)*)/i', $headers, $matches)) {
            $authResults = $matches[1];
            
            // Re-parse with more detail
            if (preg_match('/spf=(\w+)/i', $authResults, $spfMatch)) {
                $authData['spf_result'] = strtolower($spfMatch[1]);
            }
            if (preg_match('/dkim=(\w+)/i', $authResults, $dkimMatch)) {
                $authData['dkim_result'] = strtolower($dkimMatch[1]);
            }
            if (preg_match('/dmarc=(\w+)/i', $authResults, $dmarcMatch)) {
                $authData['dmarc_result'] = strtolower($dmarcMatch[1]);
            }
        }

        TestAuthentication::create($authData);
    }

    /**
     * Update test status based on results
     */
    protected function updateTestStatus(PlacementTest $test): void
    {
        $expectedAccounts = $test->expected_accounts;
        $receivedCount = TestResult::where('test_id', $test->id)->count();
        
        if ($receivedCount >= $expectedAccounts) {
            $test->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);
        } else {
            $test->update([
                'status' => 'processing',
                'results_count' => $receivedCount
            ]);
        }
    }
}
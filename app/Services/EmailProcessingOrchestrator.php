<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\Test;
use App\Models\TestResult;
use App\Services\EmailServiceFactory;
use App\Services\EmailAuthenticationParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EmailProcessingOrchestrator
{
    private $metrics = [];
    private $runId;
    
    /**
     * Traiter plusieurs comptes email
     */
    public function processAccounts($accounts, $runId, $parallel = 4, $timeout = 300, $dryRun = false)
    {
        $this->runId = $runId;
        $results = [];
        
        // Diviser les comptes en chunks pour traitement parallèle
        $chunks = $accounts->chunk($parallel);
        
        foreach ($chunks as $chunk) {
            $chunkResults = $this->processChunk($chunk, $timeout, $dryRun);
            $results = array_merge($results, $chunkResults);
        }
        
        return $results;
    }
    
    /**
     * Traiter un groupe de comptes
     */
    private function processChunk($accounts, $timeout, $dryRun)
    {
        $results = [];
        
        foreach ($accounts as $account) {
            $startTime = microtime(true);
            $metric = $this->startMetric($account);
            
            try {
                Log::info("Processing account: {$account->email}", [
                    'run_id' => $this->runId,
                    'account_id' => $account->id,
                    'provider' => $account->provider
                ]);
                
                if (!$dryRun) {
                    $result = $this->processAccount($account, $timeout);
                } else {
                    $result = $this->simulateProcessing($account);
                }
                
                $duration = round(microtime(true) - $startTime, 2);
                $this->completeMetric($metric, $result, $duration);
                
                // Log détaillé si des erreurs
                if ($result['errors_count'] > 0) {
                    Log::warning("Account processing completed with errors", [
                        'account' => $account->email,
                        'emails_found' => $result['emails_found'],
                        'emails_processed' => $result['emails_processed'],
                        'errors_count' => $result['errors_count'],
                        'status' => $result['status']
                    ]);
                }
                
                $results[] = [
                    'account_id' => $account->id,
                    'email' => $account->email,
                    'provider' => $account->provider,
                    'emails_found' => $result['emails_found'],
                    'emails_processed' => $result['emails_processed'],
                    'errors_count' => $result['errors_count'],
                    'duration' => $duration,
                    'status' => $result['status'],
                    'error_messages' => $result['error_messages'] ?? []
                ];
                
                // Réinitialiser les échecs si succès
                if ($result['status'] === 'success' && $result['emails_found'] > 0) {
                    $this->resetFailures($account);
                }
                
            } catch (\Exception $e) {
                $duration = round(microtime(true) - $startTime, 2);
                
                Log::error("Failed to process account: {$account->email}", [
                    'run_id' => $this->runId,
                    'account_id' => $account->id,
                    'error' => $e->getMessage()
                ]);
                
                $this->failMetric($metric, $e->getMessage(), $duration);
                $this->handleAccountFailure($account, $e);
                
                $results[] = [
                    'account_id' => $account->id,
                    'email' => $account->email,
                    'provider' => $account->provider,
                    'emails_found' => 0,
                    'emails_processed' => 0,
                    'errors_count' => 1,
                    'duration' => $duration,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Traiter un compte email
     */
    private function processAccount(EmailAccount $account, $timeout)
    {
        $result = [
            'emails_found' => 0,
            'emails_processed' => 0,
            'errors_count' => 0,
            'status' => 'success',
            'error_messages' => []
        ];
        
        // Obtenir les tests actifs pour ce compte
        $activeTests = $this->getActiveTestsForAccount($account);
        
        if ($activeTests->isEmpty()) {
            Log::info("No active tests for account: {$account->email}");
            return $result;
        }
        
        // Créer le service email approprié
        $emailService = EmailServiceFactory::make($account);
        
        // Pour chaque test, chercher les emails
        foreach ($activeTests as $test) {
            try {
                // Chercher les emails avec l'ID unique du test
                $emails = $emailService->searchByUniqueId($test->unique_id);
                
                if (!empty($emails)) {
                    $result['emails_found'] += count($emails);
                    
                    foreach ($emails as $emailData) {
                        try {
                            $this->processEmailForTest($test, $account, $emailData);
                            $result['emails_processed']++;
                            
                            Log::info("Email processed for test {$test->unique_id}", [
                                'account' => $account->email,
                                'placement' => $emailData['placement'] ?? 'unknown'
                            ]);
                            
                        } catch (\Exception $e) {
                            $result['errors_count']++;
                            $result['status'] = 'partial';
                            $result['error_messages'][] = "Email processing: " . $e->getMessage();
                            Log::error("Failed to process email", [
                                'test_id' => $test->unique_id,
                                'account' => $account->email,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }
                } else {
                    Log::debug("No emails found for test {$test->unique_id}", [
                        'account' => $account->email
                    ]);
                }
                
            } catch (\Exception $e) {
                $result['errors_count']++;
                $result['status'] = 'error';
                $result['error_messages'][] = "Search emails: " . $e->getMessage();
                Log::error("Failed to search emails for test {$test->unique_id}", [
                    'account' => $account->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Déterminer le statut final
        if ($result['errors_count'] > 0) {
            if ($result['emails_processed'] > 0) {
                $result['status'] = 'partial';
            } else {
                $result['status'] = 'failed';
            }
        }
        
        return $result;
    }
    
    /**
     * Traiter un email pour un test
     */
    private function processEmailForTest(Test $test, EmailAccount $account, array $emailData)
    {
        // Déterminer le placement basé sur le mapping du compte
        $placement = $this->determinePlacement($account, $emailData);
        
        // Utiliser le nouveau parser pour l'authentification
        $parser = new EmailAuthenticationParser();
        $authInfo = $parser->parseAuthentication($emailData['headers'] ?? '');
        
        // Extraire l'IP et le hostname
        $sendingIp = $parser->extractSendingIP($emailData['headers'] ?? '');
        $sendingHostname = $parser->extractHostname($emailData['headers'] ?? '');
        
        // Valider le reverse DNS si on a les deux
        $reverseDnsValid = null;
        if ($sendingIp && $sendingHostname) {
            $reverseDnsValid = (gethostbyaddr($sendingIp) === $sendingHostname);
        }
        
        // Extraire le Message-ID
        $messageId = $parser->extractMessageId($emailData['headers'] ?? '') ?? $emailData['message_id'] ?? '';
        
        // Parser l'adresse from depuis les headers (plus fiable que le champ from du service email)
        $fromInfo = $parser->extractFromInfo($emailData['headers'] ?? '');
        if (empty($fromInfo['email']) && !empty($emailData['from'])) {
            // Fallback sur le from fourni par le service email
            $fromParts = $this->parseFromAddress($emailData['from']);
            $fromInfo['email'] = $fromInfo['email'] ?? $fromParts['email'];
            $fromInfo['name'] = $fromInfo['name'] ?? $fromParts['name'];
        }
        
        // Détecter les filtres anti-spam
        $spamFilters = $this->detectSpamFilters($account, $emailData['headers'] ?? '');
        
        // Utiliser updateOrCreate pour éviter les doublons
        $testResult = TestResult::updateOrCreate(
            [
                // Clés uniques pour identifier l'enregistrement
                'test_id' => $test->id,
                'email_account_id' => $account->id,
            ],
            [
                // Données à insérer ou mettre à jour
                'message_id' => $messageId,
                'from_email' => $fromInfo['email'] ?? '',
                'from_name' => $fromInfo['name'] ?? '',
                'subject' => $emailData['subject'] ?? '',
                'body_preview' => substr($emailData['body'] ?? '', 0, 500),
                
                // Placement
                'placement' => $placement,
                'folder_name' => $emailData['folder'] ?? '',
                
                // Authentication avec le nouveau parser
                'spf_result' => $authInfo['spf']['result'] ?? 'none',
                'dkim_result' => $authInfo['dkim']['result'] ?? 'none',
                'dmarc_result' => $authInfo['dmarc']['result'] ?? 'none',
                'bimi_present' => $parser->parseBIMI($emailData['headers'] ?? ''),
                
                // IP and domain analysis
                'sending_ip' => $sendingIp,
                'sending_hostname' => $sendingHostname,
                'reverse_dns_valid' => $reverseDnsValid,
                'blacklist_results' => null, // TODO: Implement blacklist checks
                
                // Spam detection
                'spam_filters_detected' => is_array($spamFilters) ? json_encode($spamFilters) : $spamFilters,
                'spam_scores' => json_encode($this->extractSpamScores($emailData['headers'] ?? '')),
                'spam_report' => $this->extractSpamReport($emailData['headers'] ?? ''),
                
                // Email metadata
                'has_attachments' => $emailData['has_attachments'] ?? false,
                'suspicious_links' => $this->detectSuspiciousLinks($emailData['html_body'] ?? '') ? json_encode(['detected' => true]) : null,
                'size_bytes' => strlen($emailData['headers'] ?? '') + strlen($emailData['body'] ?? ''),
                
                // Raw data
                'raw_headers' => $emailData['headers'] ?? '',
                'raw_email' => $emailData['raw'] ?? null,
                'email_date' => $emailData['date'] ?? now(),
            ]
        );
        
        // Mettre à jour le pivot
        $test->emailAccounts()->updateExistingPivot($account->id, [
            'email_received' => true,
            'received_at' => now()
        ]);
        
        // Mettre à jour le statut du test
        $this->updateTestStatus($test);
    }
    
    /**
     * Déterminer le placement basé sur le mapping du compte
     */
    private function determinePlacement(EmailAccount $account, array $emailData)
    {
        $folder = strtolower($emailData['folder'] ?? '');
        
        // Vérifier le mapping des dossiers du compte
        $folderMapping = $account->folderMappings()
            ->where('folder_name', $emailData['folder'] ?? '')
            ->first();
            
        if ($folderMapping) {
            return match($folderMapping->folder_type) {
                'spam' => 'spam',
                'inbox' => 'inbox',
                'additional_inbox' => 'inbox',
                default => 'other'
            };
        }
        
        // Fallback sur la détection par nom
        if (str_contains($folder, 'spam') || str_contains($folder, 'junk')) {
            return 'spam';
        }
        
        if (str_contains($folder, 'promot')) {
            return 'promotions';
        }
        
        return 'inbox';
    }
    
    /**
     * Détecter les filtres anti-spam
     */
    private function detectSpamFilters(EmailAccount $account, string $headers)
    {
        $detected = [];
        $detectedDetails = [];
        
        // Vérifier les systèmes anti-spam configurés pour ce compte
        $antispamSystems = $account->antispamSystems()->get();
        
        foreach ($antispamSystems as $system) {
            if (!$system) continue;
            
            // Récupérer les patterns depuis la table antispam_systems
            $headerPatterns = $system->header_patterns;
            
            // Si header_patterns est une chaîne JSON, la décoder
            if (is_string($headerPatterns)) {
                $headerPatterns = json_decode($headerPatterns, true);
            }
            
            // Vérifier chaque pattern
            if (is_array($headerPatterns)) {
                foreach ($headerPatterns as $pattern) {
                    // S'assurer que le pattern est valide
                    if (empty($pattern)) continue;
                    
                    // Échapper les délimiteurs si nécessaire
                    $pattern = str_replace('/', '\/', $pattern);
                    
                    try {
                        if (preg_match('/' . $pattern . '/i', $headers, $matches)) {
                            $detected[] = $system->name;
                            
                            // Extraire le score si disponible dans le pattern
                            if ($system->score_pattern && preg_match('/' . str_replace('/', '\/', $system->score_pattern) . '/i', $headers, $scoreMatches)) {
                                $detectedDetails[$system->name] = [
                                    'detected' => true,
                                    'score' => isset($scoreMatches[1]) ? floatval($scoreMatches[1]) : null,
                                    'matched_pattern' => $pattern
                                ];
                            } else {
                                $detectedDetails[$system->name] = [
                                    'detected' => true,
                                    'matched_pattern' => $pattern
                                ];
                            }
                            break;
                        }
                    } catch (\Exception $e) {
                        \Log::warning("Invalid antispam pattern: {$pattern}", [
                            'system' => $system->name,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        // Détection par défaut basée sur les headers communs (seulement si pas déjà détecté)
        $commonSpamFilters = [
            'SpamAssassin' => '/X-Spam-(?:Status|Level|Score)/i',
            'Rspamd' => '/X-Rspamd-(?:Score|Action)/i',
            'Amavis' => '/X-Virus-Scanned.*amavis/i',
            'Barracuda' => '/X-Barracuda/i',
            'SpamTitan' => '/X-SpamTitan/i',
            'MailScanner' => '/X-.*-MailScanner/i',
            'Microsoft' => '/X-MS-Exchange-Organization-SCL/i',
            'Google' => '/X-Gm-Spam/i',
            'Proofpoint' => '/X-Proofpoint/i',
            'Mimecast' => '/X-Mimecast/i'
        ];
        
        foreach ($commonSpamFilters as $filterName => $pattern) {
            if (preg_match($pattern, $headers) && !in_array($filterName, $detected)) {
                $detected[] = $filterName;
                $detectedDetails[$filterName] = ['detected' => true, 'source' => 'auto-detect'];
            }
        }
        
        // Retourner les détails complets si disponibles, sinon juste la liste
        return !empty($detectedDetails) ? $detectedDetails : $detected;
    }
    
    /**
     * Extraire les scores de spam
     */
    private function extractSpamScores(string $headers)
    {
        $scores = [];
        
        // SpamAssassin
        if (preg_match('/X-Spam-Score:\s*([\d.-]+)/i', $headers, $matches)) {
            $scores['spamassassin'] = floatval($matches[1]);
        }
        
        // Rspamd
        if (preg_match('/X-Rspamd-Score:\s*([\d.-]+)/i', $headers, $matches)) {
            $scores['rspamd'] = floatval($matches[1]);
        }
        
        // Microsoft SCL
        if (preg_match('/X-MS-Exchange-Organization-SCL:\s*(\d+)/i', $headers, $matches)) {
            $scores['microsoft_scl'] = intval($matches[1]);
        }
        
        return $scores;
    }
    
    /**
     * Parser l'adresse from
     */
    private function parseFromAddress(string $from): array
    {
        $email = $from;
        $name = null;
        
        // Parse "Name <email@domain.com>" format
        if (preg_match('/^(.+?)\s*<(.+?)>$/', $from, $matches)) {
            $name = trim($matches[1], ' "\'');
            $email = $matches[2];
        }
        
        return ['email' => $email, 'name' => $name];
    }
    
    /**
     * Extraire le rapport spam
     */
    private function extractSpamReport(string $headers): ?string
    {
        // Extract SpamAssassin report
        if (preg_match('/X-Spam-Report:\s*(.+?)(?:\r?\n(?!\s)|$)/si', $headers, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Détecter les pixels de tracking
     */
    private function detectTrackingPixels(string $html): bool
    {
        if (empty($html)) return false;
        
        // Common tracking pixel patterns
        $patterns = [
            '/<img[^>]*width=["\']?1["\']?[^>]*height=["\']?1["\']?/i',
            '/<img[^>]*src=["\'][^"\']*\.(gif|png)[^"\']*["\'][^>]*style=["\'][^"\']*display:\s*none/i',
            '/track\.php|pixel\.gif|beacon\.gif|tracking\.php|open\.php/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Détecter les liens suspects
     */
    private function detectSuspiciousLinks(string $html): bool
    {
        if (empty($html)) return false;
        
        // URL shorteners and suspicious patterns
        $suspiciousPatterns = [
            '/bit\.ly|tinyurl\.com|goo\.gl|ow\.ly|short\.link|rebrand\.ly/i',
            '/click\.php|track\.php|redir\.php|goto\.php/i',
            '/<a[^>]*href=["\'][^"\']*["\'][^>]*style=["\'][^"\']*display:\s*none/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $html)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Mettre à jour le statut du test
     */
    private function updateTestStatus(Test $test)
    {
        $receivedCount = TestResult::where('test_id', $test->id)->count();
        $expectedCount = $test->expected_emails;
        
        $test->received_emails = $receivedCount;
        
        if ($receivedCount >= $expectedCount) {
            $test->status = 'completed';
        } elseif ($receivedCount > 0) {
            $test->status = 'in_progress';
        }
        
        $test->save();
    }
    
    /**
     * Obtenir les tests actifs pour un compte
     */
    private function getActiveTestsForAccount(EmailAccount $account)
    {
        // Récupérer tous les tests pour ce compte qui n'ont pas encore reçu l'email
        $query = Test::whereIn('status', ['pending', 'in_progress', 'completed', 'timeout'])
            ->whereHas('emailAccounts', function ($q) use ($account) {
                $q->where('email_accounts.id', $account->id)
                    ->whereNull('test_email_accounts.received_at');
            });
        
        $tests = $query->get();
        
        return $tests;
    }
    
    /**
     * Gérer l'échec d'un compte
     */
    private function handleAccountFailure(EmailAccount $account, \Exception $e)
    {
        // Mettre à jour l'erreur dans le compte email
        $account->connection_error = substr($e->getMessage(), 0, 500); // Limiter la taille
        $account->last_connection_check = now();
        $account->connection_status = 'error';
        $account->save();
        
        // Enregistrer dans la table failures si elle existe
        if (DB::getSchemaBuilder()->hasTable('email_account_failures')) {
            DB::table('email_account_failures')->updateOrInsert(
                ['email_account_id' => $account->id],
                [
                    'error_type' => 'connection',
                    'error_message' => substr($e->getMessage(), 0, 1000),
                    'failure_count' => DB::raw('COALESCE(failure_count, 0) + 1'),
                    'last_failure_at' => now(),
                    'retry_after' => now()->addMinutes(10),
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())')
                ]
            );
            
            // Vérifier si on doit désactiver le compte
            $failures = DB::table('email_account_failures')
                ->where('email_account_id', $account->id)
                ->first();
                
            if ($failures && $failures->failure_count >= 3) {
                $account->is_active = false;
                $account->save();
                
                DB::table('email_account_failures')
                    ->where('email_account_id', $account->id)
                    ->update(['auto_disabled' => true]);
                    
                Log::warning("Account auto-disabled after 3 failures", [
                    'account' => $account->email,
                    'provider' => $account->provider,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Réinitialiser les échecs d'un compte
     */
    private function resetFailures(EmailAccount $account)
    {
        // Réinitialiser le statut d'erreur dans le compte
        if ($account->connection_status === 'error') {
            $account->connection_status = 'connected';
            $account->last_connection_check = now();
            $account->save();
        }
        
        // Supprimer les failures si la table existe
        if (DB::getSchemaBuilder()->hasTable('email_account_failures')) {
            DB::table('email_account_failures')
                ->where('email_account_id', $account->id)
                ->delete();
        }
    }
    
    /**
     * Démarrer une métrique
     */
    private function startMetric(EmailAccount $account)
    {
        return DB::table('email_processing_runs')->insertGetId([
            'email_account_id' => $account->id,
            'run_id' => $this->runId,
            'started_at' => now(),
            'status' => 'running',
            'created_at' => now()
        ]);
    }
    
    /**
     * Compléter une métrique
     */
    private function completeMetric($metricId, $result, $duration)
    {
        $updateData = [
            'completed_at' => now(),
            'duration_seconds' => $duration,
            'emails_found' => $result['emails_found'],
            'emails_processed' => $result['emails_processed'],
            'errors_count' => $result['errors_count'],
            'status' => 'completed',
            'updated_at' => now()
        ];
        
        // Ajouter les messages d'erreur s'il y en a
        if (!empty($result['error_messages'])) {
            $updateData['error_details'] = substr(implode(' | ', $result['error_messages']), 0, 1000);
        }
        
        DB::table('email_processing_runs')
            ->where('id', $metricId)
            ->update($updateData);
    }
    
    /**
     * Marquer une métrique comme échouée
     */
    private function failMetric($metricId, $error, $duration)
    {
        DB::table('email_processing_runs')
            ->where('id', $metricId)
            ->update([
                'completed_at' => now(),
                'duration_seconds' => $duration,
                'status' => 'failed',
                'error_details' => substr($error, 0, 1000),
                'errors_count' => 1,
                'updated_at' => now()
            ]);
    }
    
    /**
     * Simuler le traitement (dry run)
     */
    private function simulateProcessing(EmailAccount $account)
    {
        return [
            'emails_found' => rand(0, 5),
            'emails_processed' => rand(0, 5),
            'errors_count' => 0,
            'status' => 'success'
        ];
    }
}
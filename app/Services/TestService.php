<?php

namespace App\Services;

use App\Models\Test;
use App\Models\EmailAccount;
use App\Models\TestResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TestService
{
    /**
     * Create a new test
     */
    public function createTest(array $data): Test
    {
        DB::beginTransaction();
        
        try {
            // Générer l'ID unique
            $uniqueId = $this->generateUniqueId();
            
            // Créer le test
            $test = Test::create([
                'unique_id' => $uniqueId,
                'visitor_email' => $data['visitor_email'],
                'visitor_ip' => $data['visitor_ip'] ?? request()->ip() ?? '0.0.0.0',
                'audience_type' => $data['audience_type'],
                'status' => 'pending',
                'expected_emails' => 0,
                'received_emails' => 0,
                'language' => $data['language'] ?? app()->getLocale() ?? 'fr',
                'timeout_at' => now()->addMinutes(config('mailsoar.email_check_timeout_minutes', 30)),
                'expires_at' => now()->addDays(7),
            ]);
            
            // Sélectionner les comptes email selon l'audience
            $accounts = $this->selectAccountsForTest($data['audience_type']);
            
            // Attacher les comptes au test
            foreach ($accounts as $account) {
                $test->emailAccounts()->attach($account->id, [
                    'email_received' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Mettre à jour le nombre d'emails attendus
            $test->expected_emails = $accounts->count();
            $test->save();
            
            DB::commit();
            
            return $test->load('emailAccounts');
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
    
    /**
     * Generate a unique test ID
     */
    private function generateUniqueId(): string
    {
        do {
            // Format: MS-XXXXXX (MS = MailSoar)
            $id = 'MS-' . strtoupper(Str::random(6));
        } while (Test::where('unique_id', $id)->exists());
        
        return $id;
    }
    
    /**
     * Select email accounts for the test based on audience type
     */
    private function selectAccountsForTest(string $audienceType, int $limit = 10): Collection
    {
        $accounts = collect();
        
        // Définir les providers selon le type d'audience
        $providersByType = [
            'b2c' => ['gmail', 'yahoo', 'outlook'],
            'b2b' => ['outlook', 'gmail'],
            'mixed' => ['gmail', 'outlook', 'yahoo']
        ];
        
        $providers = $providersByType[$audienceType] ?? $providersByType['mixed'];
        
        if ($audienceType === 'b2c') {
            // Pour B2C, ne prendre que des comptes B2C
            $accounts = EmailAccount::where('is_active', true)
                ->where('account_type', 'b2c')
                ->whereIn('provider', $providers)
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        } elseif ($audienceType === 'b2b') {
            // Pour B2B, ne prendre que des comptes B2B
            $accounts = EmailAccount::where('is_active', true)
                ->where('account_type', 'b2b')
                ->whereIn('provider', $providers)
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        } else {
            // Pour mixed, prendre un mélange de B2C et B2B
            $b2cCount = ceil($limit / 2);
            $b2bCount = $limit - $b2cCount;
            
            $b2cAccounts = EmailAccount::where('is_active', true)
                ->where('account_type', 'b2c')
                ->whereIn('provider', $providers)
                ->inRandomOrder()
                ->limit($b2cCount)
                ->get();
                
            $b2bAccounts = EmailAccount::where('is_active', true)
                ->where('account_type', 'b2b')
                ->whereIn('provider', $providers)
                ->inRandomOrder()
                ->limit($b2bCount)
                ->get();
                
            $accounts = $b2cAccounts->merge($b2bAccounts);
        }
        
        // Si on n'a pas assez de comptes du type demandé, compléter avec d'autres comptes
        if ($accounts->count() < $limit) {
            $remaining = $limit - $accounts->count();
            
            // Pour B2C et B2B stricts, on ne complète qu'avec le même type
            if ($audienceType === 'b2c') {
                $additionalAccounts = EmailAccount::where('is_active', true)
                    ->where('account_type', 'b2c')
                    ->whereNotIn('id', $accounts->pluck('id'))
                    ->inRandomOrder()
                    ->limit($remaining)
                    ->get();
            } elseif ($audienceType === 'b2b') {
                $additionalAccounts = EmailAccount::where('is_active', true)
                    ->where('account_type', 'b2b')
                    ->whereNotIn('id', $accounts->pluck('id'))
                    ->inRandomOrder()
                    ->limit($remaining)
                    ->get();
            } else {
                // Pour mixed, on peut prendre n'importe quel type
                $additionalAccounts = EmailAccount::where('is_active', true)
                    ->whereNotIn('id', $accounts->pluck('id'))
                    ->inRandomOrder()
                    ->limit($remaining)
                    ->get();
            }
            
            $accounts = $accounts->merge($additionalAccounts);
        }
        
        return $accounts->take($limit);
    }
}
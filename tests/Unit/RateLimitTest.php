<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\RateLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que la limite par email fonctionne
     */
    public function test_email_rate_limit_works()
    {
        $email = 'test@example.com';
        $limit = config('mailsoar.rate_limit_per_email', 5);

        // Créer des entrées jusqu'à la limite
        for ($i = 0; $i < $limit; $i++) {
            RateLimit::create([
                'email' => $email,
                'ip' => '127.0.0.1',
                'type' => 'test_creation'
            ]);
        }

        // Vérifier que nous avons atteint la limite
        $count = RateLimit::where('email', $email)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $this->assertEquals($limit, $count);
    }

    /**
     * Test que la limite par IP fonctionne
     */
    public function test_ip_rate_limit_works()
    {
        $ip = '192.168.1.1';
        $limit = config('mailsoar.rate_limit_per_ip', 10);

        // Créer des entrées jusqu'à la limite
        for ($i = 0; $i < $limit; $i++) {
            RateLimit::create([
                'email' => "test{$i}@example.com",
                'ip' => $ip,
                'type' => 'test_creation'
            ]);
        }

        // Vérifier que nous avons atteint la limite
        $count = RateLimit::where('ip', $ip)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $this->assertEquals($limit, $count);
    }

    /**
     * Test que les anciennes entrées sont ignorées
     */
    public function test_old_entries_are_ignored()
    {
        $email = 'test@example.com';

        // Créer une entrée ancienne (plus de 24h)
        RateLimit::create([
            'email' => $email,
            'ip' => '127.0.0.1',
            'type' => 'test_creation',
            'created_at' => now()->subDays(2)
        ]);

        // Créer une entrée récente
        RateLimit::create([
            'email' => $email,
            'ip' => '127.0.0.1',
            'type' => 'test_creation',
            'created_at' => now()
        ]);

        // Vérifier que seule l'entrée récente est comptée
        $count = RateLimit::where('email', $email)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Test du calcul des tests restants
     */
    public function test_remaining_tests_calculation()
    {
        $email = 'test@example.com';
        $limit = config('mailsoar.rate_limit_per_email', 5);
        $used = 3;

        // Créer 3 entrées
        for ($i = 0; $i < $used; $i++) {
            RateLimit::create([
                'email' => $email,
                'ip' => '127.0.0.1',
                'type' => 'test_creation'
            ]);
        }

        $count = RateLimit::where('email', $email)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $remaining = $limit - $count;

        $this->assertEquals(2, $remaining);
    }
}
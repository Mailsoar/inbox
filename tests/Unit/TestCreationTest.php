<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Test;
use App\Models\EmailAccount;
use App\Services\TestService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestCreationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test de création d'un test standard
     */
    public function test_can_create_standard_test()
    {
        // Créer des comptes email de test
        $this->createTestAccounts();

        $testService = new TestService();
        $test = $testService->createTest([
            'visitor_email' => 'visitor@example.com',
            'visitor_ip' => '127.0.0.1',
            'test_type' => 'standard',
            'audience_type' => 'b2c'
        ]);

        $this->assertNotNull($test);
        $this->assertEquals('visitor@example.com', $test->visitor_email);
        $this->assertEquals('standard', $test->test_type);
        $this->assertTrue($test->emailAccounts->count() > 0);
    }

    /**
     * Test que l'ID unique est généré correctement
     */
    public function test_unique_id_is_generated()
    {
        $test = Test::create([
            'visitor_email' => 'test@example.com',
            'visitor_ip' => '127.0.0.1',
            'test_type' => 'standard',
            'audience_type' => 'b2c',
            'expected_emails' => 10
        ]);

        $this->assertNotNull($test->unique_id);
        $this->assertStringStartsWith('MS-', $test->unique_id);
        $this->assertEquals(9, strlen($test->unique_id)); // MS-XXXXXX
    }

    /**
     * Test du timeout des tests
     */
    public function test_test_timeout_detection()
    {
        $test = Test::create([
            'visitor_email' => 'test@example.com',
            'visitor_ip' => '127.0.0.1',
            'test_type' => 'standard',
            'audience_type' => 'b2c',
            'expected_emails' => 10,
            'created_at' => now()->subHours(2) // Test créé il y a 2 heures
        ]);

        $this->assertTrue($test->isTimedOut());
        $this->assertEquals('timeout', $test->status);
    }

    /**
     * Test qu'un test complété est correctement détecté
     */
    public function test_completed_test_detection()
    {
        $test = Test::create([
            'visitor_email' => 'test@example.com',
            'visitor_ip' => '127.0.0.1',
            'test_type' => 'standard',
            'audience_type' => 'b2c',
            'expected_emails' => 2,
            'received_emails' => 2,
            'status' => 'completed'
        ]);

        $this->assertTrue($test->isComplete());
    }

    /**
     * Créer des comptes email de test pour les tests
     */
    private function createTestAccounts()
    {
        EmailAccount::create([
            'email' => 'test1@gmail.com',
            'provider' => 'gmail',
            'is_active' => true,
            'account_type' => 'b2c'
        ]);

        EmailAccount::create([
            'email' => 'test2@outlook.com',
            'provider' => 'outlook',
            'is_active' => true,
            'account_type' => 'b2c'
        ]);

        EmailAccount::create([
            'email' => 'test3@yahoo.com',
            'provider' => 'yahoo',
            'is_active' => true,
            'account_type' => 'b2c'
        ]);
    }
}
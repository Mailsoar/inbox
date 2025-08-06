<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\EmailProvider;
use App\Services\MxDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailProviderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test qu'un provider temporaire est bien bloqué
     */
    public function test_temporary_provider_is_blocked()
    {
        $provider = EmailProvider::create([
            'name' => 'tempmail',
            'display_name' => 'TempMail',
            'type' => 'temporary',
            'is_valid' => true,
            'detection_priority' => 100
        ]);

        $this->assertTrue($provider->isBlocked());
    }

    /**
     * Test qu'un provider B2C valide n'est pas bloqué
     */
    public function test_valid_b2c_provider_is_not_blocked()
    {
        $provider = EmailProvider::create([
            'name' => 'gmail',
            'display_name' => 'Gmail',
            'type' => 'b2c',
            'is_valid' => true,
            'detection_priority' => 1
        ]);

        $this->assertFalse($provider->isBlocked());
    }

    /**
     * Test de la détection MX pour Gmail
     */
    public function test_mx_detection_for_gmail()
    {
        // Créer le provider Gmail
        $provider = EmailProvider::create([
            'name' => 'gmail',
            'display_name' => 'Gmail',
            'type' => 'b2c',
            'is_valid' => true,
            'detection_priority' => 1
        ]);

        // Ajouter les patterns
        $provider->patterns()->create([
            'pattern' => 'gmail.com',
            'pattern_type' => 'domain'
        ]);

        $mxService = new MxDetectionService();
        $result = $mxService->analyzeEmail('test@gmail.com');

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['blocked'] ?? false);
        $this->assertEquals('gmail', $result['provider']['name'] ?? null);
    }

    /**
     * Test qu'un email blacklisté est rejeté
     */
    public function test_blacklisted_email_is_rejected()
    {
        $provider = EmailProvider::create([
            'name' => 'spammer',
            'display_name' => 'Spammer Domain',
            'type' => 'blacklisted',
            'is_valid' => false,
            'detection_priority' => 999
        ]);

        $provider->patterns()->create([
            'pattern' => 'spammer.com',
            'pattern_type' => 'domain'
        ]);

        $mxService = new MxDetectionService();
        $result = $mxService->analyzeEmail('test@spammer.com');

        $this->assertFalse($result['valid']);
        $this->assertTrue($result['blocked']);
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailProviderPattern;
use App\Models\EmailProvider;

class EmailProviderPatternSeeder extends Seeder
{
    public function run()
    {
        $patterns = [
            // Gmail patterns (provider_id = 1)
            ['provider_id' => 1, 'pattern' => 'gmail.com', 'pattern_type' => 'domain'],
            ['provider_id' => 1, 'pattern' => 'googlemail.com', 'pattern_type' => 'domain'],
            ['provider_id' => 1, 'pattern' => 'google.com', 'pattern_type' => 'mx'],
            
            // Outlook patterns (provider_id = 2)
            ['provider_id' => 2, 'pattern' => 'outlook.com', 'pattern_type' => 'domain'],
            ['provider_id' => 2, 'pattern' => 'hotmail.com', 'pattern_type' => 'domain'],
            ['provider_id' => 2, 'pattern' => 'live.com', 'pattern_type' => 'domain'],
            ['provider_id' => 2, 'pattern' => 'msn.com', 'pattern_type' => 'domain'],
            ['provider_id' => 2, 'pattern' => 'outlook.com', 'pattern_type' => 'mx'],
            
            // Yahoo patterns (provider_id = 3)
            ['provider_id' => 3, 'pattern' => 'yahoo.com', 'pattern_type' => 'domain'],
            ['provider_id' => 3, 'pattern' => 'yahoo.fr', 'pattern_type' => 'domain'],
            ['provider_id' => 3, 'pattern' => 'ymail.com', 'pattern_type' => 'domain'],
            ['provider_id' => 3, 'pattern' => 'rocketmail.com', 'pattern_type' => 'domain'],
            ['provider_id' => 3, 'pattern' => 'yahoo.com', 'pattern_type' => 'mx'],
        ];

        foreach ($patterns as $pattern) {
            // Get the provider to ensure it exists
            $provider = EmailProvider::find($pattern['provider_id']);
            if ($provider) {
                EmailProviderPattern::updateOrCreate(
                    [
                        'provider_id' => $pattern['provider_id'],
                        'pattern' => $pattern['pattern'],
                        'pattern_type' => $pattern['pattern_type']
                    ],
                    $pattern
                );
            }
        }
    }
}
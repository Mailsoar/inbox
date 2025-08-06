<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Configuration seeders (données statiques)
            AntispamSystemSeeder::class,
            ImapProviderSeeder::class,
            EmailProviderSeeder::class,
            EmailProviderPatternSeeder::class,
            FilterRulesSeeder::class,
            
            // Data seeders (peut être commenté en production)
            // EmailAccountSeeder::class,
        ]);
    }
}

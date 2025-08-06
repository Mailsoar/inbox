<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_providers', function (Blueprint $table) {
            $table->integer('max_connections_per_hour')->default(60)->after('oauth_provider');
            $table->integer('max_checks_per_connection')->default(100)->after('max_connections_per_hour');
            $table->integer('connection_backoff_minutes')->default(30)->after('max_checks_per_connection');
            $table->boolean('supports_idle')->default(false)->after('connection_backoff_minutes');
            $table->json('check_intervals')->nullable()->after('supports_idle');
        });

        // Set default values for known providers
        DB::table('email_providers')->where('name', 'yahoo')->update([
            'max_connections_per_hour' => 10,
            'max_checks_per_connection' => 50,
            'connection_backoff_minutes' => 60,
            'check_intervals' => json_encode([
                ['max_age_minutes' => 5, 'interval_minutes' => 1],
                ['max_age_minutes' => 15, 'interval_minutes' => 5],
                ['max_age_minutes' => 30, 'interval_minutes' => 15]
            ])
        ]);

        DB::table('email_providers')->where('name', 'gmail')->update([
            'max_connections_per_hour' => 60,
            'max_checks_per_connection' => 200,
            'connection_backoff_minutes' => 15,
            'supports_idle' => true,
            'check_intervals' => json_encode([
                ['max_age_minutes' => 5, 'interval_minutes' => 1],
                ['max_age_minutes' => 15, 'interval_minutes' => 5],
                ['max_age_minutes' => 30, 'interval_minutes' => 15]
            ])
        ]);

        DB::table('email_providers')->where('name', 'outlook')->update([
            'max_connections_per_hour' => 30,
            'max_checks_per_connection' => 100,
            'connection_backoff_minutes' => 30,
            'check_intervals' => json_encode([
                ['max_age_minutes' => 5, 'interval_minutes' => 1],
                ['max_age_minutes' => 15, 'interval_minutes' => 5],
                ['max_age_minutes' => 30, 'interval_minutes' => 15]
            ])
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_providers', function (Blueprint $table) {
            $table->dropColumn([
                'max_connections_per_hour',
                'max_checks_per_connection',
                'connection_backoff_minutes',
                'supports_idle',
                'check_intervals'
            ]);
        });
    }
};
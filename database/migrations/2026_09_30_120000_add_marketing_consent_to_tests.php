<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consentement du visiteur à recevoir les emails marketing de MailSoar.
 *
 * La date est conservée pour pouvoir prouver quand le consentement a été donné.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('marketing_consent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn(['marketing_consent', 'marketing_consent_at']);
        });
    }
};

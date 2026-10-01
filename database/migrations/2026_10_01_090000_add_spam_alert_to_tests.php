<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi de l'alerte Slack envoyée quand un test place trop d'emails en spam.
 *
 * La date évite d'alerter deux fois sur le même test ; l'horodatage Slack du
 * message permet de répondre dans le même fil quand le domaine refait un test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->timestamp('spam_alert_sent_at')->nullable();
            $table->string('spam_alert_slack_ts', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn(['spam_alert_sent_at', 'spam_alert_slack_ts']);
        });
    }
};

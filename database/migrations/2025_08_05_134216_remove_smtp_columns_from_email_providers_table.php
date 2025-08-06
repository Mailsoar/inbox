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
            // Supprimer les colonnes SMTP
            $table->dropColumn(['smtp_host', 'smtp_port', 'smtp_encryption']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_providers', function (Blueprint $table) {
            // Recréer les colonnes SMTP si on doit revenir en arrière
            $table->string('smtp_host')->nullable()->after('imap_encryption');
            $table->integer('smtp_port')->nullable()->after('smtp_host');
            $table->string('smtp_encryption', 10)->nullable()->after('smtp_port');
        });
    }
};
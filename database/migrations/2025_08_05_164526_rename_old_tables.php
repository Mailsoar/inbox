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
        // Renommer les tables non utilisées
        if (Schema::hasTable('imap_providers') && !Schema::hasTable('imap_providers_old')) {
            Schema::rename('imap_providers', 'imap_providers_old');
        }
        
        // email_providers_old existe déjà selon la requête précédente
        
        // Vérifier s'il y a d'autres tables à renommer
        if (Schema::hasTable('email_provider_patterns') && !Schema::hasTable('email_provider_patterns_old')) {
            // Cette table semble être liée à l'ancienne structure
            Schema::rename('email_provider_patterns', 'email_provider_patterns_old');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurer les noms originaux
        if (Schema::hasTable('imap_providers_old') && !Schema::hasTable('imap_providers')) {
            Schema::rename('imap_providers_old', 'imap_providers');
        }
        
        if (Schema::hasTable('email_provider_patterns_old') && !Schema::hasTable('email_provider_patterns')) {
            Schema::rename('email_provider_patterns_old', 'email_provider_patterns');
        }
    }
};

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
        // Renommer les tables non utilisées (vides) vers _old
        $tablesToRename = [
            'received_emails' => 'received_emails_old',  // test_results est utilisé à la place
            'rate_limits' => 'rate_limits_old',          // verification_rate_limits est utilisé à la place
            'personal_access_tokens' => 'personal_access_tokens_old', // Non utilisé
            'password_reset_tokens' => 'password_reset_tokens_old',   // Non utilisé  
            'users' => 'users_old'                       // Non utilisé (système de vérification différent)
        ];
        
        foreach ($tablesToRename as $oldName => $newName) {
            if (Schema::hasTable($oldName) && !Schema::hasTable($newName)) {
                Schema::rename($oldName, $newName);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurer les noms originaux
        $tablesToRestore = [
            'received_emails_old' => 'received_emails',
            'rate_limits_old' => 'rate_limits',
            'personal_access_tokens_old' => 'personal_access_tokens',
            'password_reset_tokens_old' => 'password_reset_tokens',
            'users_old' => 'users'
        ];
        
        foreach ($tablesToRestore as $oldName => $newName) {
            if (Schema::hasTable($oldName) && !Schema::hasTable($newName)) {
                Schema::rename($oldName, $newName);
            }
        }
    }
};

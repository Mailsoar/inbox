<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Étape 1: Migrer les données vers les champs unifiés
        $this->migrateConnectionData();
        
        // Étape 2: Supprimer les colonnes redondantes
        Schema::table('email_accounts', function (Blueprint $table) {
            // Supprimer les champs de statut redondants
            $table->dropColumn([
                'last_connection_status',
                'last_connection_test', 
                'last_connection_attempt',
                'last_successful_sync',
                'last_check_at',
                'last_error',
                'last_error_at',
                'last_connection_error'
            ]);
        });
        
        // Étape 3: S'assurer que les champs gardés ont les bonnes contraintes
        Schema::table('email_accounts', function (Blueprint $table) {
            // Mise à jour des champs conservés
            $table->enum('connection_status', ['success', 'failed', 'error', 'connecting', 'unknown'])
                  ->default('unknown')
                  ->change();
            
            $table->timestamp('last_connection_check')->nullable()->change();
            $table->text('connection_error')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurer les colonnes supprimées
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->string('last_connection_status')->nullable();
            $table->timestamp('last_connection_test')->nullable();
            $table->timestamp('last_connection_attempt')->nullable();
            $table->timestamp('last_successful_sync')->nullable();
            $table->timestamp('last_check_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_connection_error')->nullable();
        });
    }

    /**
     * Migrer les données existantes vers les champs unifiés
     */
    private function migrateConnectionData(): void
    {
        $accounts = DB::table('email_accounts')->get();
        
        foreach ($accounts as $account) {
            $updates = [];
            
            // 1. Unifier le statut de connexion
            $connectionStatus = $account->connection_status ?? $account->last_connection_status ?? 'unknown';
            $updates['connection_status'] = $connectionStatus;
            
            // 2. Unifier le timestamp de dernière vérification (prendre le plus récent)
            $timestamps = array_filter([
                $account->last_connection_check,
                $account->last_connection_test,
                $account->last_check_at,
                $account->last_connection_attempt,
            ]);
            
            if (!empty($timestamps)) {
                $updates['last_connection_check'] = max($timestamps);
            }
            
            // 3. Unifier le message d'erreur
            $connectionError = $account->connection_error 
                            ?? $account->last_connection_error 
                            ?? $account->last_error 
                            ?? null;
            
            if ($connectionError) {
                $updates['connection_error'] = substr($connectionError, 0, 500); // Limiter à 500 chars
            }
            
            // Appliquer les mises à jour
            if (!empty($updates)) {
                DB::table('email_accounts')
                    ->where('id', $account->id)
                    ->update($updates);
            }
        }
        
        echo "✅ Données migrées pour " . count($accounts) . " comptes\n";
    }
};
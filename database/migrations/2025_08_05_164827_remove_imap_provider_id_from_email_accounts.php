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
        // Vérifier si la colonne existe avant de la supprimer
        if (Schema::hasColumn('email_accounts', 'imap_provider_id')) {
            // Essayer de supprimer la foreign key si elle existe (ignorer l'erreur)
            try {
                Schema::table('email_accounts', function (Blueprint $table) {
                    $table->dropForeign(['imap_provider_id']);
                });
            } catch (\Exception $e) {
                // Ignorer si la foreign key n'existe pas
                \Log::info('Foreign key does not exist, continuing...');
            }
            
            // Supprimer la colonne
            Schema::table('email_accounts', function (Blueprint $table) {
                $table->dropColumn('imap_provider_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            // Rétablir la colonne
            $table->unsignedBigInteger('imap_provider_id')->nullable();
            // Rétablir la foreign key (vers la table renommée)
            $table->foreign('imap_provider_id')->references('id')->on('imap_providers_old');
        });
    }
};

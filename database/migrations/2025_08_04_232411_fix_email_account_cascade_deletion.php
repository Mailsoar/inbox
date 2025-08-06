<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. Supprimer les contraintes CASCADE existantes
        Schema::table('test_email_accounts', function (Blueprint $table) {
            $table->dropForeign(['email_account_id']);
        });
        
        Schema::table('received_emails', function (Blueprint $table) {
            $table->dropForeign(['email_account_id']);
        });
        
        // 2. Recréer les contraintes SANS cascade (SET NULL)
        Schema::table('test_email_accounts', function (Blueprint $table) {
            // Pour test_email_accounts, on ne peut pas mettre NULL car c'est une table pivot
            // On va utiliser RESTRICT pour empêcher la suppression si des tests existent
            $table->foreign('email_account_id')
                ->references('id')
                ->on('email_accounts')
                ->onDelete('restrict');
        });
        
        // 3. Pour received_emails, permettre NULL et SET NULL on delete
        Schema::table('received_emails', function (Blueprint $table) {
            // D'abord rendre la colonne nullable
            $table->unsignedBigInteger('email_account_id')->nullable()->change();
            
            // Puis ajouter la contrainte avec SET NULL
            $table->foreign('email_account_id')
                ->references('id')
                ->on('email_accounts')
                ->onDelete('set null');
        });
        
        // 4. Ajouter soft delete aux email_accounts
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at');
        });
        
        // Log pour information
        DB::statement("-- Fixed cascade deletion: email_accounts now use soft delete, tests are preserved");
    }

    public function down()
    {
        // Retirer soft deletes
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        
        // Restaurer les anciennes contraintes CASCADE
        Schema::table('test_email_accounts', function (Blueprint $table) {
            $table->dropForeign(['email_account_id']);
            $table->foreign('email_account_id')
                ->references('id')
                ->on('email_accounts')
                ->onDelete('cascade');
        });
        
        Schema::table('received_emails', function (Blueprint $table) {
            $table->dropForeign(['email_account_id']);
            $table->unsignedBigInteger('email_account_id')->nullable(false)->change();
            $table->foreign('email_account_id')
                ->references('id')
                ->on('email_accounts')
                ->onDelete('cascade');
        });
    }
};
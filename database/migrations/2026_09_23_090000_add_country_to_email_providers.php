<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pays où chaque fournisseur opère sa messagerie (code ISO 3166-1
     * alpha-2), affiché en drapeau à côté des boîtes dans les résultats.
     */
    public function up(): void
    {
        Schema::table('email_providers', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('provider_type');
        });

        $countries = [
            'us' => ['gmail', 'outlook', 'yahoo', 'proofpoint', 'apple_icloud', 'icloud', 'amazon_workmail'],
            'fr' => ['laposte', 'orange', 'sfr', 'free', 'ovh', 'gandi'],
            'de' => ['webde', 'gmx', 'ionos'],
            'in' => ['zoho'],
        ];

        foreach ($countries as $country => $names) {
            DB::table('email_providers')->whereIn('name', $names)->update(['country' => $country]);
        }
    }

    public function down(): void
    {
        Schema::table('email_providers', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};

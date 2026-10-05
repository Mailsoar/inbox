<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact HubSpot créé ou mis à jour lors de l'alerte spam du test.
 *
 * Sert aussi de verrou : une nouvelle tentative de l'alerte ne resynchronise
 * pas le contact, ce qui compterait l'alerte deux fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->string('hubspot_contact_id', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn('hubspot_contact_id');
        });
    }
};

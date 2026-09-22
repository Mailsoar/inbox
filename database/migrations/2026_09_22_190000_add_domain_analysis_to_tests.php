<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stocke l'analyse d'authentification du domaine d'envoi.
 *
 * Le document est figé au moment du test : il reflète la configuration telle
 * qu'elle était à l'envoi, ce qui est précisément ce qu'on veut diagnostiquer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->string('sending_domain', 255)->nullable();
            $table->jsonb('domain_analysis')->nullable();
            $table->timestamp('domain_analyzed_at')->nullable();

            $table->index('sending_domain');
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropIndex(['sending_domain']);
            $table->dropColumn(['sending_domain', 'domain_analysis', 'domain_analyzed_at']);
        });
    }
};

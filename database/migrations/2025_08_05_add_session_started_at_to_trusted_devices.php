<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->timestamp('session_started_at')->nullable()->after('last_used_at');
        });
    }

    public function down()
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->dropColumn('session_started_at');
        });
    }
};
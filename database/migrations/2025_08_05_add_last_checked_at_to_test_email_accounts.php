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
        Schema::table('test_email_accounts', function (Blueprint $table) {
            $table->timestamp('last_checked_at')->nullable()->after('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_email_accounts', function (Blueprint $table) {
            $table->dropColumn('last_checked_at');
        });
    }
};
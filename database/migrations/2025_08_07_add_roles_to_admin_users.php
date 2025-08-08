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
        Schema::table('admin_users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'admin', 'viewer'])
                  ->default('viewer')
                  ->after('email')
                  ->comment('User role: super_admin (full access), admin (manage), viewer (read-only)');
            
            $table->json('permissions')
                  ->nullable()
                  ->after('role')
                  ->comment('Additional granular permissions in JSON format');
                  
            $table->index('role');
        });
        
        // Set existing admins as super_admin
        DB::table('admin_users')->update(['role' => 'super_admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['role', 'permissions']);
        });
    }
};
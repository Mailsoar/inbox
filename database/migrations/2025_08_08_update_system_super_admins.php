<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get system-defined super admin emails from config
        $superAdminEmails = explode(',', env('GOOGLE_ALLOWED_EMAILS', ''));
        $superAdminEmails = array_map('trim', $superAdminEmails);
        
        // Update these users to super_admin role if they exist
        if (!empty($superAdminEmails)) {
            DB::table('admin_users')
                ->whereIn('email', $superAdminEmails)
                ->update([
                    'role' => 'super_admin',
                    'updated_at' => now()
                ]);
                
            \Log::info('System super admins updated', [
                'emails' => $superAdminEmails
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to do - we don't want to remove super admin roles
    }
};
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AdminUser;

class TestAuthConfiguration extends Command
{
    protected $signature = 'auth:test-config';
    protected $description = 'Test authentication configuration and show system super admins';

    public function handle()
    {
        $this->info('Authentication Configuration Test');
        $this->info('=================================');
        
        // Show configured domains
        $allowedDomains = explode(',', config('services.google.allowed_domains', ''));
        $allowedDomains = array_map('trim', $allowedDomains);
        
        $this->info("\nAllowed Domains (GOOGLE_ALLOWED_DOMAINS):");
        foreach ($allowedDomains as $domain) {
            $this->line("  - @{$domain}");
        }
        
        // Show system super admins
        $superAdminEmails = explode(',', config('services.google.allowed_emails', ''));
        $superAdminEmails = array_map('trim', $superAdminEmails);
        
        $this->info("\nSystem Super Admins (GOOGLE_ALLOWED_EMAILS):");
        if (empty($superAdminEmails) || (count($superAdminEmails) === 1 && $superAdminEmails[0] === '')) {
            $this->warn("  No system super admins configured");
        } else {
            foreach ($superAdminEmails as $email) {
                $this->line("  - {$email}");
            }
        }
        
        // Show current admin users
        $this->info("\nCurrent Admin Users in Database:");
        $admins = AdminUser::orderBy('role')->orderBy('email')->get();
        
        if ($admins->isEmpty()) {
            $this->warn("  No admin users found");
        } else {
            foreach ($admins as $admin) {
                $roleColor = match($admin->role) {
                    'super_admin' => 'error',
                    'admin' => 'warn',
                    default => 'info'
                };
                
                $status = $admin->is_active ? '✓' : '✗';
                $protected = $admin->isSystemSuperAdmin() ? ' [PROTECTED]' : '';
                
                $this->line(sprintf(
                    "  %s %-40s <%s> %s%s",
                    $status,
                    $admin->email,
                    $admin->role ?: 'no role',
                    $admin->getRoleDisplayName(),
                    $protected
                ), $roleColor);
            }
        }
        
        // Test sample emails
        $this->info("\nSample Email Tests:");
        $testEmails = [
            'pierre.galiegue@mailsoar.com',
            'test@mailsoar.com',
            'external@gmail.com',
        ];
        
        foreach ($testEmails as $email) {
            $emailDomain = substr(strrchr($email, "@"), 1);
            $isAllowedDomain = in_array($emailDomain, $allowedDomains);
            $isSystemAdmin = in_array($email, $superAdminEmails);
            
            $this->line(sprintf(
                "  %-40s Domain: @%-15s [%s] System Admin: [%s]",
                $email,
                $emailDomain,
                $isAllowedDomain ? '✓ Allowed' : '✗ Denied',
                $isSystemAdmin ? '✓ Yes' : '✗ No'
            ));
        }
        
        return 0;
    }
}
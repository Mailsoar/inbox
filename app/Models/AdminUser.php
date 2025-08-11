<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminUser extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'google_id',
        'avatar',
        'is_active',
        'last_login_at',
        'dashboard_period',
        'role',
        'permissions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'permissions' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isAllowedEmail(): bool
    {
        // Check if user is from allowed domain
        $allowedDomains = explode(',', config('services.google.allowed_domains', ''));
        $allowedDomains = array_map('trim', $allowedDomains);
        
        $emailDomain = substr(strrchr($this->email, "@"), 1);
        
        // Check if email domain is allowed
        if (in_array($emailDomain, $allowedDomains)) {
            return true;
        }
        
        // Also check if email is in the super admin list
        return $this->isSystemSuperAdmin();
    }
    
    /**
     * Check if user is a system-defined super admin (from env)
     */
    public function isSystemSuperAdmin(): bool
    {
        $superAdminEmails = explode(',', config('services.google.allowed_emails', ''));
        $superAdminEmails = array_map('trim', $superAdminEmails);
        
        return in_array($this->email, $superAdminEmails);
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
    
    /**
     * Check if user is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
    
    /**
     * Check if user is at least an admin
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }
    
    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        // Super admins have all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Check role-based permissions
        $rolePermissions = $this->getRolePermissions();
        if (in_array($permission, $rolePermissions)) {
            return true;
        }
        
        // Check custom permissions
        if ($this->permissions && in_array($permission, $this->permissions)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get default permissions for the user's role
     */
    protected function getRolePermissions(): array
    {
        return match($this->role) {
            'super_admin' => [
                'view_all',
                'manage_tests',
                'manage_email_accounts',
                'manage_providers',
                'manage_admins',
                'delete_data',
                'view_logs',
                'run_commands',
                'system_config',
            ],
            'admin' => [
                'view_all',
                'manage_tests',
                'manage_email_accounts',
                'manage_providers',
                'view_logs',
            ],
            'viewer' => [
                'view_all',
                'manage_tests',
            ],
            default => [],
        };
    }
    
    /**
     * Get role display name
     */
    public function getRoleDisplayName(): string
    {
        return match($this->role) {
            'super_admin' => 'Super Administrator',
            'admin' => 'Administrator',
            'viewer' => 'Viewer',
            default => 'Unknown',
        };
    }
}
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\AdminUser;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Define gates for admin permissions
        
        Gate::define('view-dashboard', function ($user) {
            return $user instanceof AdminUser && $user->hasPermission('view_all');
        });
        
        Gate::define('manage-tests', function ($user) {
            return $user instanceof AdminUser && $user->hasPermission('manage_tests');
        });
        
        Gate::define('delete-tests', function ($user) {
            return $user instanceof AdminUser && $user->hasPermission('delete_data');
        });
        
        Gate::define('manage-email-accounts', function ($user) {
            return $user instanceof AdminUser && $user->hasPermission('manage_email_accounts');
        });
        
        Gate::define('delete-email-accounts', function ($user) {
            return $user instanceof AdminUser && $user->hasPermission('delete_data');
        });
        
        Gate::define('manage-providers', function ($user) {
            return $user instanceof AdminUser && $user->hasPermission('manage_providers');
        });
        
        Gate::define('manage-admins', function ($user) {
            return $user instanceof AdminUser && $user->hasPermission('manage_admins');
        });
        
        Gate::define('view-logs', function ($user) {
            return $user instanceof AdminUser && $user->hasPermission('view_logs');
        });
        
        Gate::define('run-commands', function ($user) {
            return $user instanceof AdminUser && $user->hasPermission('run_commands');
        });
        
        Gate::define('system-config', function ($user) {
            return $user instanceof AdminUser && $user->hasPermission('system_config');
        });
        
        // Helper gate to check if user is at least an admin
        Gate::define('is-admin', function ($user) {
            return $user instanceof AdminUser && $user->isAdmin();
        });
        
        // Helper gate to check if user is super admin
        Gate::define('is-super-admin', function ($user) {
            return $user instanceof AdminUser && $user->isSuperAdmin();
        });
    }
}

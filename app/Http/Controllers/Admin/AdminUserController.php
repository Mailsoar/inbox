<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminUserController extends Controller
{
    /**
     * Display a listing of admin users.
     */
    public function index()
    {
        $admin = auth('admin')->user();
        
        if (!$admin || !$admin->hasPermission('manage_admins')) {
            abort(403, 'Unauthorized');
        }
        
        $admins = AdminUser::orderBy('role')->orderBy('name')->get();
        
        return view('admin.users.index', compact('admins'));
    }
    
    /**
     * Show the form for editing an admin user.
     */
    public function edit(AdminUser $admin)
    {
        $currentAdmin = auth('admin')->user();
        
        if (!$currentAdmin || !$currentAdmin->hasPermission('manage_admins')) {
            abort(403, 'Unauthorized');
        }
        
        return view('admin.users.edit', compact('admin'));
    }
    
    /**
     * Update the specified admin user.
     */
    public function update(Request $request, AdminUser $admin)
    {
        $currentAdmin = auth('admin')->user();
        
        if (!$currentAdmin || !$currentAdmin->hasPermission('manage_admins')) {
            abort(403, 'Unauthorized');
        }
        
        // Prevent modification of system-defined super admins
        if ($admin->isSystemSuperAdmin()) {
            return back()->with('error', 'Cannot modify system-defined super admin. This user is protected by system configuration.');
        }
        
        // Prevent self-demotion for super admins
        if ($admin->id === auth('admin')->id() && 
            $admin->role === 'super_admin' && 
            $request->role !== 'super_admin') {
            return back()->with('error', 'You cannot change your own super admin role.');
        }
        
        // Validate that there's always at least one super admin
        if ($admin->role === 'super_admin' && $request->role !== 'super_admin') {
            $superAdminCount = AdminUser::where('role', 'super_admin')->count();
            if ($superAdminCount <= 1) {
                return back()->with('error', 'There must be at least one super admin.');
            }
        }
        
        try {
            $validated = $request->validate([
                'role' => 'required|in:super_admin,admin,viewer',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|in:view_all,manage_tests,manage_email_accounts,manage_providers,manage_admins,delete_data,view_logs,run_commands,system_config'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('[AdminUserController] Validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            throw $e;
        }
        
        // Log for debugging
        \Log::info('[AdminUserController] Updating admin user', [
            'target_admin_id' => $admin->id,
            'target_admin_email' => $admin->email,
            'current_role' => $admin->role,
            'new_role' => $validated['role'],
            'updated_by' => $currentAdmin->email,
            'is_active' => $request->has('is_active')
        ]);
        
        $admin->update([
            'role' => $validated['role'],
            'is_active' => $request->has('is_active'),
            'permissions' => $validated['permissions'] ?? [],
        ]);
        
        return redirect()->route('admin.users.index')
            ->with('success', 'Admin user updated successfully.');
    }
    
    /**
     * Toggle admin active status (AJAX)
     */
    public function toggleActive(AdminUser $admin)
    {
        $currentAdmin = auth('admin')->user();
        
        if (!$currentAdmin || !$currentAdmin->hasPermission('manage_admins')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        // Prevent modification of system-defined super admins
        if ($admin->isSystemSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify system-defined super admin.'
            ], 400);
        }
        
        // Prevent self-deactivation
        if ($admin->id === auth('admin')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own account.'
            ], 400);
        }
        
        // Ensure at least one active super admin
        if ($admin->role === 'super_admin' && $admin->is_active) {
            $activeSuperAdmins = AdminUser::where('role', 'super_admin')
                ->where('is_active', true)
                ->count();
            
            if ($activeSuperAdmins <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'There must be at least one active super admin.'
                ], 400);
            }
        }
        
        $admin->update(['is_active' => !$admin->is_active]);
        
        return response()->json([
            'success' => true,
            'is_active' => $admin->is_active
        ]);
    }
}
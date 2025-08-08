<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAdminPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        $admin = Auth::guard('admin')->user();
        
        if (!$admin) {
            return redirect()->route('admin.login')
                ->with('error', 'Please login to continue.');
        }
        
        if (!$admin->hasPermission($permission)) {
            // Log unauthorized access attempt
            \Log::warning('Unauthorized access attempt', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'permission' => $permission,
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);
            
            if ($request->ajax()) {
                return response()->json([
                    'error' => 'You do not have permission to perform this action.'
                ], 403);
            }
            
            return redirect()->route('admin.dashboard')
                ->with('error', 'You do not have permission to access this area.');
        }
        
        return $next($request);
    }
}
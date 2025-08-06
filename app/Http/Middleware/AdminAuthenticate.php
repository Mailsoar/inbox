<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        \Log::info('AdminAuthenticate middleware', [
            'is_logged_in' => Auth::guard('admin')->check(),
            'user_id' => Auth::guard('admin')->id(),
            'session_id' => session()->getId(),
            'url' => $request->url()
        ]);
        
        if (!Auth::guard('admin')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
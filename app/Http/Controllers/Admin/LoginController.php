<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (auth('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }
        
        return view('admin.login-simple');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Vérification simple pour démonstration
        // EN PRODUCTION: utiliser une vraie authentification
        $allowedEmails = explode(',', config('services.google.allowed_emails', ''));
        $allowedEmails = array_map('trim', $allowedEmails);
        
        if (!in_array($request->email, $allowedEmails)) {
            return back()->with('error', 'Email non autorisé pour l\'administration.');
        }

        // Créer ou récupérer l'utilisateur admin
        $adminUser = AdminUser::firstOrCreate(
            ['email' => $request->email],
            [
                'name' => explode('@', $request->email)[0],
                'is_active' => true,
            ]
        );

        $adminUser->updateLastLogin();
        
        // Connexion de l'utilisateur admin
        Auth::guard('admin')->login($adminUser);
        
        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('home')->with('success', 'Déconnexion réussie.');
    }
}
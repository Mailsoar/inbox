<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->scopes(['email', 'profile'])
            ->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            // Vérifier si l'email est autorisé
            $allowedEmails = explode(',', config('services.google.allowed_emails', ''));
            $allowedEmails = array_map('trim', $allowedEmails);
            
            // Debug : log des informations
            \Log::info('Google Auth Attempt', [
                'email' => $googleUser->getEmail(),
                'allowed_emails' => $allowedEmails,
                'is_allowed' => in_array($googleUser->getEmail(), $allowedEmails)
            ]);
            
            if (!in_array($googleUser->getEmail(), $allowedEmails)) {
                return redirect()->route('home')->with('error', 'Accès non autorisé. Votre email (' . $googleUser->getEmail() . ') n\'est pas dans la liste des administrateurs autorisés.');
            }
            
            // Créer ou mettre à jour l'utilisateur admin
            // Tronquer l'URL de l'avatar si elle est trop longue
            $avatarUrl = $googleUser->getAvatar();
            if (strlen($avatarUrl) > 255) {
                // Extraire juste l'URL de base sans les paramètres
                $avatarUrl = strtok($avatarUrl, '?');
                if (strlen($avatarUrl) > 255) {
                    $avatarUrl = null; // Si toujours trop long, ne pas stocker
                }
            }
            
            $adminUser = AdminUser::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $avatarUrl,
                    'is_active' => true,
                ]
            );
            
            if (!$adminUser->isAllowedEmail()) {
                return redirect()->route('home')->with('error', 'Accès non autorisé.');
            }
            
            $adminUser->updateLastLogin();
            
            // Connexion de l'utilisateur admin
            Auth::guard('admin')->login($adminUser, true); // true pour "remember me"
            
            // Forcer la régénération de la session
            session()->regenerate();
            
            // Debug : vérifier la connexion
            \Log::info('Admin login status after login', [
                'is_logged_in' => Auth::guard('admin')->check(),
                'user_id' => Auth::guard('admin')->id(),
                'session_id' => session()->getId()
            ]);
            
            // Vérifier s'il y a un compte email en attente
            if (session()->has('pending_email_account_id')) {
                $emailAccountId = session('pending_email_account_id');
                session()->forget('pending_email_account_id');
                
                return redirect()
                    ->route('admin.email-accounts.edit', $emailAccountId)
                    ->with('success', 'Connexion réussie. Veuillez finaliser la configuration du compte email.');
            }
            
            return redirect()->intended(route('admin.dashboard'))->with('success', 'Connexion réussie.');
            
        } catch (\Exception $e) {
            \Log::error('Google Auth Error: ' . $e->getMessage());
            return redirect()->route('home')->with('error', 'Erreur lors de l\'authentification Google.');
        }
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('home')->with('success', 'Déconnexion réussie.');
    }
}
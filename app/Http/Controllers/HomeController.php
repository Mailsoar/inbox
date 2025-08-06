<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RateLimit;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        // Gérer le changement de langue
        if ($request->has('lang')) {
            $language = in_array($request->lang, ['fr', 'en']) ? $request->lang : 'fr';
            session(['language' => $language]);
            app()->setLocale($language);
        } else {
            // Utiliser la langue de session ou détecter depuis le navigateur
            $language = session('language', $this->detectLanguage($request));
            app()->setLocale($language);
        }
        
        return view('welcome');
    }
    
    private function detectLanguage(Request $request)
    {
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage && str_contains(strtolower($acceptLanguage), 'fr')) {
            return 'fr';
        }
        return 'en';
    }
}
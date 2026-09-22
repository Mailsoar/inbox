<?php

namespace App\Http\Controllers;

use App\Services\PublicTestPreview;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, PublicTestPreview $preview)
    {
        if ($request->has('lang')) {
            $language = in_array($request->lang, ['fr', 'en']) ? $request->lang : 'fr';
            session(['language' => $language]);
            app()->setLocale($language);
        } else {
            $language = session('language', $this->detectLanguage($request));
            return redirect('/?lang=' . $language);
        }

        // La seed list et l'identifiant sont affichés avant la création du test :
        // le visiteur copie l'un et l'autre, envoie son email, puis lance.
        return view('welcome', [
            'trackingId' => $preview->trackingId(),
            'seedList' => $preview->seedList(),
        ]);
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

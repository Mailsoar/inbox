<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\EmailAccount;
use App\Models\EmailProvider;
use App\Models\VerificationRateLimit;
use App\Models\TrustedDevice;
use App\Services\TestService;
use App\Rules\ValidEmailProvider;
use App\Helpers\EmailHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Jenssegers\Agent\Agent;

class TestController extends Controller
{
    protected $testService;

    public function __construct(TestService $testService)
    {
        $this->testService = $testService;
    }

    /**
     * Afficher le formulaire de création de test
     */
    public function create(Request $request)
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
        
        // Vérifier si l'utilisateur est authentifié pour pré-remplir l'email
        $prefilledEmail = null;
        if ($this->isAuthenticated()) {
            $prefilledEmail = session('verified_email');
        }
        
        return view('test.create', compact('prefilledEmail'));
    }

    /**
     * Check rate limits for an email
     */
    public function checkLimits(Request $request)
    {
        $email = $request->input('email');
        
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid email'
            ]);
        }
        
        $remaining = VerificationVerificationRateLimit::getRemaining('email', $email);
        $limit = config('mailsoar.rate_limit_per_email', 50);
        
        return response()->json([
            'valid' => true,
            'remaining' => $remaining,
            'limit' => $limit,
            'percentage' => round(($remaining / $limit) * 100)
        ]);
    }


    /**
     * Créer un nouveau test (formulaire POST)
     */
    public function store(Request $request)
    {
        if ($request->ajax()) {
            Log::info('[TEST_CREATE] Store method started', [
                'email' => $request->input('visitor_email'),
                'ip' => $request->ip(),
                'time' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Vérifier reCAPTCHA en premier
            $recaptchaToken = $request->input('g-recaptcha-response');
            if ($recaptchaToken) {
                Log::info('[TEST_CREATE] Verifying reCAPTCHA');
                $recaptchaResponse = $this->verifyRecaptcha($recaptchaToken);
                if (!$recaptchaResponse['success'] || ($recaptchaResponse['score'] ?? 0) < 0.5) {
                    Log::warning('[TEST_CREATE] reCAPTCHA failed');
                    return response()->json([
                        'success' => false,
                        'message' => 'Verification failed. Please try again.',
                        'error_code' => 'RECAPTCHA_FAILED'
                    ], 200);
                }
            }
            
            $email = $request->input('visitor_email');
            $normalizedEmail = $email ? EmailHelper::normalize($email) : null;
            $ip = $request->ip();
            
            // Vérifier si l'IP est bloquée
            if (EmailHelper::isIpBlocked($ip)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied from this IP address.',
                    'error_code' => 'IP_BLOCKED'
                ], 200);
            }
            
            // Vérifier si le domaine email est bloqué
            if ($email && EmailHelper::isBlocked($email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This email domain is not allowed.',
                    'error_code' => 'DOMAIN_BLOCKED'
                ], 200);
            }
            
            if ($normalizedEmail) {
                $emailRemaining = VerificationRateLimit::getRemaining('email', $normalizedEmail);
                
                if ($emailRemaining === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Daily limit reached for this email. Please try again tomorrow or use a different email.',
                        'rate_limit' => [
                            'allowed' => false,
                            'remaining' => 0,
                            'limit' => config('mailsoar.rate_limit_per_email', 10)
                        ],
                        'error_code' => 'RATE_LIMIT_EXCEEDED',
                        'is_rate_limited' => true
                    ], 200);
                }
            }
            
            $ipRemaining = VerificationRateLimit::getRemaining('ip', $ip);
            
            if ($ipRemaining === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daily limit reached for this IP address. Please try again tomorrow.',
                    'rate_limit' => [
                        'allowed' => false,
                        'remaining' => 0,
                        'limit' => config('mailsoar.rate_limit_per_ip', 20)
                    ],
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                    'is_rate_limited' => true
                ], 200);
            }
            
            $validator = Validator::make($request->all(), [
                'visitor_email' => ['required', 'email', 'max:255', new ValidEmailProvider()],
                'audience_type' => 'required|in:b2c,b2b,mixed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Please check your input and try again.',
                    'error_code' => 'VALIDATION_ERROR'
                ], 200);
            }

            $email = $request->input('visitor_email');
            $audienceType = $request->input('audience_type');
            
            if (strlen($ip) > 255) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
                if (strlen($ip) > 255) {
                    $ip = substr($ip, 0, 255);
                }
            }
            
            $emailCheck = VerificationRateLimit::checkAndIncrement('email', $email);
            
            if (!$emailCheck['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daily limit reached for this email (' . $emailCheck['limit'] . ' tests per day). Please try again tomorrow or use a different email.',
                    'rate_limit' => $emailCheck,
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                    'is_rate_limited' => true
                ], 200);
            }

            $ipCheck = VerificationRateLimit::checkAndIncrement('ip', $ip);
            
            if (!$ipCheck['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daily limit reached for this IP address (' . $ipCheck['limit'] . ' tests per day). Please try again tomorrow.',
                    'rate_limit' => $ipCheck,
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                    'is_rate_limited' => true
                ], 200);
            }
            
            try {
                Log::info('[TEST_CREATE] Checking email accounts');
                $accountCount = EmailAccount::where('is_active', true)->count();
                
                if ($accountCount === 0) {
                    Log::error('[TEST_CREATE] No accounts available');
                    return response()->json([
                        'success' => false,
                        'message' => 'No email accounts available for testing. Please contact support.',
                        'error_code' => 'NO_ACCOUNTS_AVAILABLE'
                    ], 200);
                }
                
                Log::info('[TEST_CREATE] Creating test', ['accounts' => $accountCount]);
                $test = $this->testService->createTest([
                    'visitor_email' => $email,
                    'visitor_ip' => $ip,
                    'audience_type' => $audienceType,
                    'test_size' => min(config('mailsoar.default_test_size', 25), $accountCount),
                ]);
                Log::info('[TEST_CREATE] Test created successfully', ['test_id' => $test->unique_id]);
                
                $seedEmails = $test->emailAccounts->map(function ($account) {
                    return [
                        'email' => $account->email,
                        'provider' => $account->getRealProvider(),
                        'type' => $account->account_type ?? 'mixed',
                    ];
                })->toArray();
                
                return response()->json([
                    'success' => true,
                    'test_id' => $test->unique_id,
                    'seed_emails' => $seedEmails,
                    'timeout_minutes' => config('mailsoar.email_check_timeout_minutes', 30),
                    'message' => 'Test created successfully!'
                ]);
            } catch (\Exception $e) {
                Log::error('[TEST_CREATE] Test creation failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'email' => $email,
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error creating test: ' . $e->getMessage(),
                    'error_code' => 'TEST_CREATION_ERROR'
                ], 200);
            }
        }

        $test = Test::where('unique_id', $request->input('test_id'))->first();
        if (!$test) {
            return redirect()->route('test.create')->with('error', 'Test not found.');
        }

        return redirect()->route('test.results', $test->unique_id);
    }

    /**
     * Afficher les instructions du test
     */
    public function instructions(Request $request, $uniqueId)
    {
        // Gérer le changement de langue
        if ($request->has('lang')) {
            $language = in_array($request->lang, ['fr', 'en']) ? $request->lang : 'fr';
            session(['language' => $language]);
            app()->setLocale($language);
        } else {
            $language = session('language', $this->detectLanguage($request));
            app()->setLocale($language);
        }
        
        $test = Test::where('unique_id', $uniqueId)
            ->with(['emailAccounts'])
            ->firstOrFail();

        return view('test.instructions', compact('test'));
    }

    /**
     * Afficher les résultats du test
     */
    public function results(Request $request, $uniqueId)
    {
        // Gérer le changement de langue
        if ($request->has('lang')) {
            $language = in_array($request->lang, ['fr', 'en']) ? $request->lang : 'fr';
            session(['language' => $language]);
            app()->setLocale($language);
        } else {
            $language = session('language', $this->detectLanguage($request));
            app()->setLocale($language);
        }
        
        $test = Test::where('unique_id', $uniqueId)
            ->with(['emailAccounts', 'results.emailAccount'])
            ->firstOrFail();

        // Générer des données fictives si aucun résultat n'existe
        // Fonctionnalité de génération de résultats fictifs désactivée
        // NOTE: Cette fonctionnalité était utilisée pour la démonstration
        // if ($test->receivedEmails->isEmpty() && $test->status === 'pending') {
        //     // Simuler un délai de traitement
        //     $test->status = 'in_progress';
        //     $test->save();
        //     
        //     // Générer les résultats fictifs
        //     $this->testService->generateFakeResults($test);
        // }

        // Récupérer la liste des providers disponibles
        $providers = collect();
        
        // Ajouter les providers OAuth natifs
        $providers->push((object)['name' => 'gmail', 'display_name' => 'Gmail']);
        $providers->push((object)['name' => 'outlook', 'display_name' => 'Outlook']);
        $providers->push((object)['name' => 'yahoo', 'display_name' => 'Yahoo']);
        
        // Ajouter les providers depuis la base de données
        $dbProviders = \DB::table('email_providers')
            ->where('is_active', 1)
            ->whereNotIn('name', ['gmail', 'outlook', 'yahoo']) // Exclure ceux déjà ajoutés
            ->select('name', 'display_name', 'domains', 'mx_patterns')
            ->orderBy('display_name')
            ->get();
            
        $providers = $providers->merge($dbProviders);
        
        // Trier par nom d'affichage
        $providers = $providers->sortBy('display_name')->values();

        return view('test.results', compact('test', 'providers'));
    }

    public function show($uniqueId)
    {
        $test = Test::where('unique_id', $uniqueId)
            ->with(['emailAccounts', 'results.emailAccount'])
            ->firstOrFail();

        // Vérifier si le test est expiré
        if ($test->isExpired()) {
            abort(410, 'Ce test a expiré.');
        }

        return view('test.show', compact('test'));
    }

    public function stream($uniqueId)
    {
        $test = Test::where('unique_id', $uniqueId)->firstOrFail();

        if ($test->isExpired()) {
            abort(410);
        }

        return new StreamedResponse(function () use ($test) {
            while (true) {
                // Recharger le test
                $test->refresh();
                $test->load(['emailAccounts', 'receivedEmails.emailAccount']);

                // Envoyer les données
                echo "data: " . json_encode([
                    'status' => $test->status,
                    'received_emails' => $test->received_emails,
                    'expected_emails' => $test->expected_emails,
                    'is_complete' => $test->isComplete(),
                    'is_timed_out' => $test->isTimedOut(),
                    'accounts' => $test->emailAccounts->map(function ($account) use ($test) {
                        $received = $test->receivedEmails->where('email_account_id', $account->id)->first();
                        return [
                            'id' => $account->id,
                            'email' => $account->email,
                            'provider' => $account->provider,
                            'received' => !is_null($received),
                            'placement' => $received?->placement,
                            'received_at' => $received?->created_at->toIso8601String(),
                        ];
                    }),
                ]) . "\n\n";

                ob_flush();
                flush();

                // Si le test est terminé ou expiré, arrêter
                if ($test->isComplete() || $test->isTimedOut()) {
                    break;
                }

                // Attendre 2 secondes avant la prochaine vérification
                sleep(2);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }


    /**
     * Cancel a test
     */
    public function cancel(Request $request, $uniqueId)
    {
        $test = Test::where('unique_id', $uniqueId)->first();
        
        if (!$test) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Test not found.'
                ], 404);
            }
            return redirect()->route('test.create')->with('error', 'Test not found.');
        }

        // Check if test belongs to the visitor (by email or IP)
        if ($test->visitor_email !== $request->input('visitor_email') && 
            $test->visitor_ip !== $request->ip()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to cancel this test.'
                ], 403);
            }
            return redirect()->route('test.create')->with('error', 'Unauthorized.');
        }

        // Mark test as cancelled
        $test->status = 'cancelled';
        $test->save();

        // Optionally, delete the test and related data
        // $test->receivedEmails()->delete();
        // $test->emailAccounts()->detach();
        // $test->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Test cancelled successfully.'
            ]);
        }

        return redirect()->route('test.create')->with('success', 'Test cancelled successfully.');
    }

    /**
     * Demander l'accès aux tests - Étape 1 : Email
     */
    public function requestAccess(Request $request)
    {
        $language = $this->getLanguage($request);
        app()->setLocale($language);

        // Vérifier si l'utilisateur est déjà authentifié
        if ($this->isAuthenticated()) {
            return redirect()->route('test.my-tests-authenticated');
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'email' => 'required|email',
                'g-recaptcha-response' => 'required',
            ]);

            // Vérifier reCAPTCHA
            $recaptchaResponse = $this->verifyRecaptcha($request->input('g-recaptcha-response'));
            if (!$recaptchaResponse['success']) {
                return back()->withErrors(['recaptcha' => 'Verification failed. Please try again.']);
            }

            $email = $request->input('email');
            $normalizedEmail = EmailHelper::normalize($email);
            $ip = $request->ip();

            // Vérifier si l'IP est bloquée
            if (EmailHelper::isIpBlocked($ip)) {
                return back()->withErrors(['error' => 'Accès refusé depuis cette adresse IP.']);
            }

            // Vérifier si le domaine email est bloqué
            if (EmailHelper::isBlocked($email)) {
                return back()->withErrors(['error' => 'Ce domaine email n\'est pas autorisé.']);
            }

            // Vérifier les limites avec l'email normalisé
            $rateCheck = \App\Services\VerificationRateLimiter::canRequest($normalizedEmail, $ip);
            if (!$rateCheck['allowed']) {
                return back()->withErrors(['rate_limit' => $rateCheck['message']]);
            }

            // Générer et envoyer le code
            $code = \App\Models\EmailVerification::generateCode();
            $verification = \App\Models\EmailVerification::create([
                'email' => $email,
                'code' => $code,
                'ip_address' => $ip,
                'expires_at' => now()->addHour(),
            ]);

            // Incrémenter les compteurs avec l'email normalisé
            \App\Services\VerificationRateLimiter::increment($normalizedEmail, $ip);

            // Envoyer l'email
            \Mail::to($email)->send(new \App\Mail\VerificationCodeMail($code, $language));

            // Sauvegarder la préférence de langue
            \App\Models\UserPreference::updateOrCreate(
                ['email' => $email],
                ['language' => $language]
            );

            // Rediriger vers la page de vérification
            return redirect()->route('test.verify-code', ['email' => $email]);
        }

        return view('test.request-access', compact('language'));
    }

    /**
     * Vérifier le code - Étape 2
     */
    public function verifyCode(Request $request)
    {
        // L'email peut venir soit de l'URL query string, soit du formulaire POST
        $email = $request->input('email') ?? $request->query('email');
        if (!$email) {
            return redirect()->route('test.request-access');
        }

        $language = $this->getLanguage($request);
        app()->setLocale($language);

        if ($request->isMethod('post')) {
            $request->validate([
                'code' => 'required|digits:6',
            ]);

            // Debug logging
            \Log::info('Code verification attempt', [
                'email' => $email,
                'code' => $request->input('code'),
                'ip' => $request->ip()
            ]);

            $verification = \App\Models\EmailVerification::where('email', $email)
                ->where('code', $request->input('code'))
                ->where('verified_at', null)
                ->latest()
                ->first();

            if (!$verification) {
                // Log pour debug
                \Log::warning('Code verification failed - not found', [
                    'email' => $email,
                    'code' => $request->input('code'),
                    'existing_codes' => \App\Models\EmailVerification::where('email', $email)
                        ->where('verified_at', null)
                        ->pluck('code')
                ]);
                return back()->withErrors(['code' => __('messages.verification.invalid_code')]);
            }

            if ($verification->isExpired()) {
                return back()->withErrors(['code' => __('messages.verification.expired_code')]);
            }

            // Marquer comme vérifié et créer la session
            $verification->markAsVerified();

            // Créer une session sécurisée avec empreinte du navigateur
            session([
                'verified_email' => $email,
                'verified_token' => $verification->session_token,
                'verified_at' => now(),
                'verified_user_agent' => $request->userAgent(),
                'verified_ip' => $request->ip(),
            ]);

            // TOUJOURS créer un appareil de confiance avec une durée fixe de 30 jours
            try {
                    // Détection du navigateur avec fallback
                    $browser = 'Unknown';
                    $platform = 'Unknown';
                    
                    try {
                        $agent = new Agent();
                        $agent->setUserAgent($request->userAgent());
                        $browser = $agent->browser() ?: 'Unknown';
                        $platform = $agent->platform() ?: 'Unknown';
                    } catch (\Exception $e) {
                        // Fallback si Agent ne fonctionne pas
                        $userAgent = $request->userAgent();
                        if (stripos($userAgent, 'chrome') !== false) $browser = 'Chrome';
                        elseif (stripos($userAgent, 'firefox') !== false) $browser = 'Firefox';
                        elseif (stripos($userAgent, 'safari') !== false) $browser = 'Safari';
                        elseif (stripos($userAgent, 'edge') !== false) $browser = 'Edge';
                        
                        if (stripos($userAgent, 'windows') !== false) $platform = 'Windows';
                        elseif (stripos($userAgent, 'mac') !== false) $platform = 'macOS';
                        elseif (stripos($userAgent, 'linux') !== false) $platform = 'Linux';
                        elseif (stripos($userAgent, 'android') !== false) $platform = 'Android';
                        elseif (stripos($userAgent, 'iphone') !== false || stripos($userAgent, 'ipad') !== false) $platform = 'iOS';
                    }
                    
                    // Durée fixe de 30 jours
                    $expirationDays = 30;
                    $expirationMinutes = 60 * 24 * 30; // 30 jours en minutes
                    
                    $trustedDevice = TrustedDevice::create([
                        'email' => $email,
                        'token' => TrustedDevice::generateToken(),
                        'device_name' => null, // Peut être défini plus tard par l'utilisateur
                        'browser' => $browser,
                        'platform' => $platform,
                        'ip_address' => $request->ip(),
                        'last_used_at' => now(),
                        'session_started_at' => now(), // Sauvegarder l'heure de début de session
                        'expires_at' => now()->addDays($expirationDays),
                    ]);
                    
                    // Créer le cookie sécurisé
                    // Utiliser la même configuration que les sessions pour la cohérence
                    $isSecure = config('session.secure', false);
                    if (empty($isSecure) && str_starts_with(config('app.url'), 'https://')) {
                        $isSecure = true;
                    }
                    
                    $cookie = cookie(
                        'trusted_device',
                        $trustedDevice->token,
                        $expirationMinutes, // 30 jours
                        null,
                        null,
                        $isSecure, // secure basé sur la config
                        true, // httpOnly
                        false, // raw
                        'lax' // sameSite
                    );
                    
                    return redirect()->route('test.my-tests-authenticated')->withCookie($cookie);
                } catch (\Exception $e) {
                    \Log::error('Erreur lors de la création du trusted device', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'email' => $email,
                        'user_agent' => $request->userAgent(),
                        'ip' => $request->ip()
                    ]);
                    
                    // Continuer sans le cookie en cas d'erreur
                    return redirect()->route('test.my-tests-authenticated');
                }
            
            // Ne pas créer de cookie si erreur
            return redirect()->route('test.my-tests-authenticated');
        }

        return view('test.verify-code', compact('email', 'language'));
    }

    /**
     * Afficher les tests après vérification
     */
    public function myTestsAuthenticated(Request $request)
    {
        // Vérifier l'authentification complète (session + token en DB)
        if (!$this->isAuthenticated()) {
            return redirect()->route('test.request-access');
        }

        $email = session('verified_email');
        $language = $this->getLanguage($request);
        app()->setLocale($language);
        
        // Récupérer les limites
        $emailRemaining = VerificationRateLimit::getRemaining('email', $email);
        $emailLimit = config('mailsoar.rate_limit_per_email', 50);
        
        // Calculer le temps de renouvellement
        $rateLimit = VerificationRateLimit::where('type', 'email')
            ->where('identifier', $email)
            ->first();
        
        $resetTime = null;
        if ($rateLimit) {
            $resetTime = $rateLimit->reset_at;
        }
        
        // Récupérer les tests
        $tests = Test::where('visitor_email', $email)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function($test) {
                $receivedCount = $test->receivedEmails()->count();
                $totalAccounts = $test->emailAccounts()->count();
                
                return [
                    'unique_id' => $test->unique_id,
                    'audience_type' => $test->audience_type,
                    'status' => $test->status,
                    'created_at' => $test->created_at,
                    'received_count' => $receivedCount,
                    'total_accounts' => $totalAccounts,
                    'completion_rate' => $totalAccounts > 0 ? round(($receivedCount / $totalAccounts) * 100, 1) : 0,
                    'inbox_count' => $test->getInboxCount(),
                    'spam_count' => $test->getSpamCount(),
                ];
            });

        return view('test.my-tests', compact('tests', 'email', 'language', 'emailRemaining', 'emailLimit', 'resetTime'));
    }

    /**
     * Déconnecter l'utilisateur
     */
    public function logout(Request $request)
    {
        // Récupérer et supprimer l'appareil de confiance si présent
        $trustedToken = $request->cookie('trusted_device');
        if ($trustedToken) {
            TrustedDevice::where('token', $trustedToken)->delete();
        }
        
        // Effacer toutes les données de session liées à la vérification
        session()->forget(['verified_email', 'verified_token', 'verified_at', 'verified_user_agent', 'verified_ip']);
        
        // Supprimer le cookie d'appareil de confiance
        $cookie = cookie()->forget('trusted_device');
        
        // Rediriger vers la page d'accueil
        return redirect()->route('home')->withCookie($cookie)->with('success', __('messages.general.logged_out'));
    }


    /**
     * Obtenir la langue préférée
     */
    private function getLanguage(Request $request): string
    {
        // 1. Vérifier le paramètre de requête
        if ($request->has('lang') && in_array($request->query('lang'), ['fr', 'en'])) {
            $lang = $request->query('lang');
            session(['locale' => $lang]);
            return $lang;
        }

        // 2. Vérifier la session
        if (session()->has('locale')) {
            return session('locale');
        }

        // 3. Vérifier la préférence sauvegardée (si email disponible)
        $email = $request->input('email') ?? $request->query('email') ?? session('verified_email');
        if ($email) {
            $preference = \App\Models\UserPreference::where('email', $email)->first();
            if ($preference) {
                return $preference->language;
            }
        }

        // 4. Détecter depuis le navigateur
        $browserLang = substr($request->server('HTTP_ACCEPT_LANGUAGE', 'fr'), 0, 2);
        return in_array($browserLang, ['fr', 'en']) ? $browserLang : 'fr';
    }

    /**
     * Vérifier reCAPTCHA
     */
    private function verifyRecaptcha($token): array
    {
        $response = \Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret'),
            'response' => $token,
        ]);

        return $response->json();
    }

    /**
     * Vérifier si l'utilisateur est authentifié
     */
    private function isAuthenticated()
    {
        // 1. D'abord vérifier le cookie d'appareil de confiance
        $trustedToken = request()->cookie('trusted_device');
        if ($trustedToken) {
            $trustedDevice = TrustedDevice::where('token', $trustedToken)
                ->active()
                ->first();
            
            if ($trustedDevice) {
                // Mettre à jour la dernière utilisation
                $trustedDevice->updateLastUsed();
                
                // Restaurer la session si elle n'existe pas
                if (!session()->has('verified_email')) {
                    // Pour un trusted device, on peut restaurer la session
                    // SANS créer de nouvelle vérification
                    $sessionStartedAt = $trustedDevice->session_started_at ?? $trustedDevice->created_at;
                    
                    session([
                        'verified_email' => $trustedDevice->email,
                        'verified_token' => 'trusted_device_' . $trustedDevice->token, // Token spécial pour trusted device
                        'verified_at' => $sessionStartedAt,
                        'verified_user_agent' => request()->userAgent(),
                        'verified_ip' => request()->ip(),
                    ]);
                }
                
                return true;
            }
        }
        
        // 2. Ensuite vérifier la session classique
        // Vérifier si la session contient les informations d'authentification
        if (!session()->has('verified_email') || !session()->has('verified_token') || !session()->has('verified_at')) {
            return false;
        }

        // Vérifier l'empreinte du navigateur (protection contre le vol de session)
        if (session()->has('verified_user_agent')) {
            $currentUserAgent = request()->userAgent();
            $sessionUserAgent = session('verified_user_agent');
            if ($currentUserAgent !== $sessionUserAgent) {
                // User-agent différent = session potentiellement volée
                session()->forget(['verified_email', 'verified_token', 'verified_at', 'verified_user_agent', 'verified_ip']);
                return false;
            }
        }

        // Vérifier que la session n'est pas expirée (12 heures)
        $verifiedAt = session('verified_at');
        if ($verifiedAt instanceof \Carbon\Carbon) {
            if ($verifiedAt->addHours(12)->isPast()) {
                // Session expirée, la nettoyer
                session()->forget(['verified_email', 'verified_token', 'verified_at']);
                return false;
            }
        } else if (is_string($verifiedAt)) {
            // Si c'est une chaîne, la convertir en Carbon
            $verifiedAt = \Carbon\Carbon::parse($verifiedAt);
            if ($verifiedAt->addHours(12)->isPast()) {
                session()->forget(['verified_email', 'verified_token', 'verified_at']);
                return false;
            }
        }

        // Vérifier que le token est valide
        $email = session('verified_email');
        $token = session('verified_token');
        
        // Si c'est un token de trusted device, on fait confiance
        if (str_starts_with($token, 'trusted_device_')) {
            return true;
        }
        
        // Sinon, vérifier dans la base de données
        $verification = \App\Models\EmailVerification::where('email', $email)
            ->where('session_token', $token)
            ->whereNotNull('verified_at')
            ->first();

        return $verification !== null;
    }

    /**
     * Détecter la langue depuis le navigateur
     */
    private function detectLanguage(Request $request)
    {
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage && str_contains(strtolower($acceptLanguage), 'fr')) {
            return 'fr';
        }
        return 'en';
    }

    /**
     * Afficher les appareils de confiance
     */
    public function trustedDevices(Request $request)
    {
        // Vérifier l'authentification
        if (!$this->isAuthenticated()) {
            return redirect()->route('test.request-access');
        }

        $email = session('verified_email');
        $language = $this->getLanguage($request);
        app()->setLocale($language);

        // Récupérer les appareils de confiance
        $devices = TrustedDevice::where('email', $email)
            ->active()
            ->orderBy('last_used_at', 'desc')
            ->get();

        // Identifier l'appareil actuel
        $currentToken = $request->cookie('trusted_device');

        return view('test.trusted-devices', compact('devices', 'email', 'language', 'currentToken'));
    }

    /**
     * Supprimer un appareil de confiance
     */
    public function removeTrustedDevice(Request $request, $deviceId)
    {
        // Vérifier l'authentification
        if (!$this->isAuthenticated()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $email = session('verified_email');
        
        // Récupérer l'appareil
        $device = TrustedDevice::where('email', $email)
            ->where('id', $deviceId)
            ->first();

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        // Supprimer l'appareil
        $device->delete();

        // Si c'est l'appareil actuel, déconnecter l'utilisateur
        $currentToken = $request->cookie('trusted_device');
        if ($currentToken && $device->token === $currentToken) {
            session()->forget(['verified_email', 'verified_token', 'verified_at', 'verified_user_agent', 'verified_ip']);
            $cookie = cookie()->forget('trusted_device');
            return response()->json(['success' => true, 'logout' => true])->withCookie($cookie);
        }

        return response()->json(['success' => true]);
    }

}
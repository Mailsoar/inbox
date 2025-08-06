<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\AntispamSystem;
use App\Models\EmailFolderMapping;
use App\Models\EmailProvider;
use App\Services\EmailServiceFactory;
use App\Services\HeaderAnalyzer;
use App\Services\MxDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmailAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailAccount::with(['receivedEmails' => function($q) {
            $q->where('created_at', '>=', now()->subDays(30));
        }]);

        // Filtres
        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('email', 'like', '%' . $request->search . '%')
                  ->orWhere('name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('connection')) {
            $query->where('connection_status', $request->connection);
        }

        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        $accounts = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Calculate emails received in last 30 days for each account
        foreach ($accounts as $account) {
            $account->emails_last_30_days = $account->receivedEmails()->count();
        }

        // Statistiques pour les filtres
        $providers = EmailAccount::select('provider')
            ->distinct()
            ->pluck('provider')
            ->sort()
            ->values();

        $totalAccounts = EmailAccount::count();
        $activeAccounts = EmailAccount::where('is_active', true)->count();
        $oauthAccounts = EmailAccount::where('auth_type', 'oauth')->count();
        $connectedAccounts = EmailAccount::where('connection_status', 'success')->count();

        // Récupérer tous les providers pour la détection
        $emailProviders = \App\Models\EmailProvider::active()->get();

        return view('admin.email-accounts.index', compact(
            'accounts',
            'providers',
            'totalAccounts',
            'activeAccounts',
            'oauthAccounts',
            'connectedAccounts',
            'emailProviders'
        ));
    }

    public function create()
    {
        $providers = ['gmail', 'outlook', 'yahoo', 'imap'];
        $imapProviders = EmailProvider::where('is_active', true)->orderBy('display_name')->get();
        
        return view('admin.email-accounts.create', compact('providers', 'imapProviders'));
    }

    public function store(Request $request)
    {
        Log::info('EmailAccount store called', [
            'all_data' => $request->all(),
            'provider' => $request->provider,
            'email' => $request->email
        ]);

        $rules = [
            'email' => 'required|email|unique:email_accounts,email',
            'provider' => 'required|in:gmail,outlook,yahoo,imap',
            'name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];

        // Validation supplémentaire selon le provider
        if ($request->provider === 'yahoo' || $request->provider === 'imap') {
            $rules['password'] = 'required|string';
        }

        if ($request->provider === 'imap') {
            $rules['imap_host'] = 'required|string';
            $rules['imap_port'] = 'required|integer';
            $rules['imap_encryption'] = 'required|in:ssl,tls,none';
        }

        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', [
                'errors' => $e->errors(),
                'data' => $request->all()
            ]);
            throw $e;
        }

        $data = [
            'email' => $request->email,
            'provider' => $request->provider,
            'name' => $request->name,
            'is_active' => false, // IMPORTANT: Jamais actif par défaut
        ];

        // Définir le auth_type selon le provider
        switch ($request->provider) {
            case 'gmail':
                $data['auth_type'] = 'oauth';
                break;
            case 'outlook':
                $data['auth_type'] = 'oauth';
                break;
            case 'yahoo':
                $data['auth_type'] = 'password';
                break;
            case 'imap':
                $data['auth_type'] = 'imap';
                break;
            default:
                $data['auth_type'] = 'password';
        }

        // Ajouter les données spécifiques selon le provider
        if ($request->provider === 'yahoo' || $request->provider === 'imap') {
            $data['password'] = encrypt($request->password);
        }

        if ($request->provider === 'imap') {
            $data['imap_settings'] = [
                'host' => $request->imap_host,
                'port' => $request->imap_port,
                'encryption' => $request->imap_encryption,
            ];
        }

        // Analyser l'email pour déterminer le type de compte
        $mxService = new MxDetectionService();
        $recommendation = $mxService->getRecommendation($data['email']);
        
        // Appliquer le type de compte recommandé si non spécifié
        if (!isset($data['account_type'])) {
            $data['account_type'] = $recommendation['recommended_type'];
        }
        
        try {
            $account = EmailAccount::create($data);
            
            // Attacher les systèmes antispam détectés
            if (!empty($recommendation['detected_systems'])) {
                foreach ($recommendation['detected_systems'] as $system) {
                    $account->antispamSystems()->attach($system['id'], [
                        'detected_at' => now()
                    ]);
                }
            }
            
            Log::info('Account created successfully', [
                'account_id' => $account->id,
                'email' => $account->email,
                'provider' => $account->provider,
                'account_type' => $account->account_type,
                'detected_systems' => $recommendation['detected_systems'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create account', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            return redirect()
                ->route('admin.email-accounts.create')
                ->withInput()
                ->with('error', 'Erreur lors de la création du compte : ' . $e->getMessage());
        }

        // Test de connexion immédiat pour Yahoo et IMAP
        if (in_array($request->provider, ['yahoo', 'imap'])) {
            try {
                $emailService = EmailServiceFactory::make($account);
                $testResult = $emailService->testConnection();
                
                if (isset($testResult['success']) ? $testResult['success'] : ($testResult['status'] ?? false)) {
                    $account->update([
                        'connection_status' => 'success',
                        'last_connection_check' => now(),
                    ]);
                    
                    session()->flash('success', 'Connexion vérifiée avec succès. Veuillez finaliser la configuration du compte.');
                    
                    // Forcer la redirection avec JavaScript si le serveur retourne toujours 200
                    return response()->view('admin.email-accounts.redirect', [
                        'url' => route('admin.email-accounts.edit', $account),
                        'message' => 'Redirection vers la configuration du compte...'
                    ]);
                } else {
                    $account->update(['connection_status' => 'failed']);
                    
                    session()->flash('warning', 'Compte créé mais la connexion a échoué : ' . ($testResult['error'] ?? 'Erreur inconnue'));
                    
                    return response()->view('admin.email-accounts.redirect', [
                        'url' => route('admin.email-accounts.edit', $account),
                        'message' => 'Redirection vers la configuration du compte...'
                    ]);
                }
            } catch (\Exception $e) {
                $account->update(['connection_status' => 'failed']);
                
                session()->flash('warning', 'Compte créé mais erreur lors du test : ' . $e->getMessage());
                
                return response()->view('admin.email-accounts.redirect', [
                    'url' => route('admin.email-accounts.edit', $account),
                    'message' => 'Redirection vers la configuration du compte...'
                ]);
            }
        }

        // Pour Gmail et Outlook, on ne devrait jamais arriver ici
        // car ils passent par OAuth
        session()->flash('warning', 'Compte créé. Veuillez finaliser la configuration.');
        
        return response()->view('admin.email-accounts.redirect', [
            'url' => route('admin.email-accounts.edit', $account),
            'message' => 'Redirection vers la configuration du compte...'
        ]);
    }

    public function edit(EmailAccount $emailAccount)
    {
        $recentEmails = $emailAccount->receivedEmails()
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $stats = [
            'total_emails' => $emailAccount->receivedEmails()->count(),
            'recent_emails' => $recentEmails->count(),
            'inbox_rate' => $this->calculateInboxRate($emailAccount),
            'spam_rate' => $this->calculateSpamRate($emailAccount),
            'last_email' => $emailAccount->receivedEmails()->latest()->first(),
        ];

        return view('admin.email-accounts.edit', compact('emailAccount', 'recentEmails', 'stats'));
    }

    public function update(Request $request, EmailAccount $emailAccount)
    {
        // Si le compte est connecté mais pas actif, on ne devrait pas pouvoir le modifier ici
        if (!$emailAccount->is_active && $emailAccount->connection_status === 'success') {
            return redirect()
                ->route('admin.email-accounts.configure-antispam', $emailAccount)
                ->with('info', 'Veuillez d\'abord finaliser la configuration anti-spam.');
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'account_type' => 'required|in:b2c,b2b',
        ]);

        $wasInactive = !$emailAccount->is_active;
        
        $emailAccount->update([
            'name' => $request->name,
            'is_active' => $request->boolean('is_active'),
            'account_type' => $request->account_type,
        ]);

        // Si le compte vient d'être activé, rediriger vers la configuration anti-spam
        if ($wasInactive && $request->boolean('is_active')) {
            return redirect()
                ->route('admin.email-accounts.configure-antispam', $emailAccount)
                ->with('success', 'Compte activé avec succès. Configurons maintenant la détection anti-spam.');
        }

        return redirect()
            ->route('admin.email-accounts.index')
            ->with('success', 'Compte email mis à jour avec succès.');
    }

    public function destroy(EmailAccount $emailAccount)
    {
        // Vérifier qu'il n'y a pas de tests récents
        $recentTests = $emailAccount->receivedEmails()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($recentTests > 0) {
            return redirect()
                ->route('admin.email-accounts.index')
                ->with('error', 'Impossible de supprimer un compte avec des tests récents.');
        }

        $emailAccount->delete();

        return redirect()
            ->route('admin.email-accounts.index')
            ->with('success', 'Compte email supprimé avec succès.');
    }
    
    /**
     * Obtenir une recommandation MX pour une adresse email
     */
    public function getMxRecommendation(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);
        
        $mxService = new MxDetectionService();
        $recommendation = $mxService->getRecommendation($request->email);
        
        return response()->json($recommendation);
    }

    public function testConnection(Request $request, EmailAccount $emailAccount)
    {
        try {
            Log::info('Testing connection for email account', [
                'account_id' => $emailAccount->id,
                'email' => $emailAccount->email,
                'provider' => $emailAccount->provider
            ]);
            
            $emailService = EmailServiceFactory::make($emailAccount);
            
            // Test de connexion
            $result = $emailService->testConnection();
            
            Log::info('Connection test result', [
                'account_id' => $emailAccount->id,
                'email' => $emailAccount->email,
                'result' => $result
            ]);
            
            if (isset($result['success']) ? $result['success'] : ($result['status'] ?? false)) {
                $emailAccount->update([
                    'last_connection_check' => now(),
                    'connection_status' => 'success',
                ]);
                
                // Si requête AJAX, retourner JSON
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Connexion réussie pour ' . $emailAccount->email . '. ' . ($result['message'] ?? '')
                    ]);
                }
                
                return redirect()
                    ->route('admin.email-accounts.index')
                    ->with('success', 'Connexion réussie pour ' . $emailAccount->email);
            } else {
                Log::warning('Connection test failed', [
                    'account_id' => $emailAccount->id,
                    'email' => $emailAccount->email,
                    'error_message' => $result['message'] ?? $result['error'] ?? 'Erreur inconnue',
                    'full_result' => $result
                ]);
                
                $emailAccount->update([
                    'last_connection_check' => now(),
                    'connection_status' => 'failed',
                ]);
                
                // Si requête AJAX, retourner JSON
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Échec de la connexion : ' . ($result['message'] ?? $result['error'] ?? 'Erreur inconnue')
                    ]);
                }
                
                return redirect()
                    ->route('admin.email-accounts.index')
                    ->with('error', 'Échec de la connexion pour ' . $emailAccount->email . ': ' . ($result['error'] ?? 'Erreur inconnue'));
            }
        } catch (\Exception $e) {
            Log::error('Test connection failed', [
                'account_id' => $emailAccount->id,
                'error' => $e->getMessage()
            ]);

            // Si requête AJAX, retourner JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors du test de connexion: ' . $e->getMessage()
                ]);
            }

            return redirect()
                ->route('admin.email-accounts.index')
                ->with('error', 'Erreur lors du test de connexion: ' . $e->getMessage());
        }
    }

    private function calculateInboxRate(EmailAccount $account): float
    {
        $totalEmails = $account->receivedEmails()->count();
        if ($totalEmails === 0) return 0;

        $inboxEmails = $account->receivedEmails()
            ->whereIn('placement', \App\Models\Test::getInboxPlacements())
            ->count();

        return round(($inboxEmails / $totalEmails) * 100, 1);
    }

    private function calculateSpamRate(EmailAccount $account): float
    {
        $totalEmails = $account->receivedEmails()->count();
        if ($totalEmails === 0) return 0;

        $spamEmails = $account->receivedEmails()
            ->where('placement', 'spam')
            ->count();

        return round(($spamEmails / $totalEmails) * 100, 1);
    }

    /**
     * Show anti-spam configuration form
     */
    public function configureAntispam(EmailAccount $emailAccount)
    {
        Log::info('Starting antispam configuration', [
            'account_id' => $emailAccount->id,
            'email' => $emailAccount->email,
            'provider' => $emailAccount->provider
        ]);
        
        $detectionData = [
            'folders' => [],
            'emails' => [],
            'detected_antispam' => null,
            'suggested_mapping' => null,
            'existing_mappings' => $emailAccount->folderMappings()->get(),
            'existing_antispam' => $emailAccount->antispamSystems()->pluck('antispam_systems.id')->toArray(),
        ];

        try {
            // Pour Gmail, utiliser des labels prédéfinis au lieu de récupérer des dossiers
            if ($emailAccount->provider === 'gmail') {
                $detectionData['folders'] = [
                    'INBOX',
                    'INBOX (Primary)',
                    'INBOX (Promotions)',
                    'INBOX (Social)',
                    'INBOX (Updates)',
                    'INBOX (Forums)',
                    '[Gmail]/Spam',
                    'TRASH',
                    'SENT',
                    'DRAFT',
                    'IMPORTANT',
                    'STARRED',
                    'UNREAD'
                ];
                
                Log::info('Gmail folders set for antispam configuration', [
                    'account_id' => $emailAccount->id,
                    'email' => $emailAccount->email,
                    'folders_count' => count($detectionData['folders']),
                    'folders' => $detectionData['folders']
                ]);
            } else {
                // Pour les autres providers, essayer de récupérer les dossiers
                $emailService = EmailServiceFactory::make($emailAccount);
                
                // Vérifier si le service a une méthode getFolders
                if (method_exists($emailService, 'getFolders')) {
                    try {
                        $folders = $emailService->getFolders();
                        Log::info('Folders retrieved for ' . $emailAccount->provider, [
                            'account_id' => $emailAccount->id,
                            'folders' => $folders
                        ]);
                        
                        if (!empty($folders)) {
                            $detectionData['folders'] = array_column($folders, 'name');
                        } else {
                            // Si aucun dossier trouvé, utiliser les valeurs par défaut
                            Log::warning('No folders found for ' . $emailAccount->provider . ', using defaults');
                            $detectionData['folders'] = [
                                'INBOX',
                                'Junk',
                                'Junk Email',
                                'Spam',
                                'Trash',
                                'Sent',
                                'Drafts'
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Error getting folders for ' . $emailAccount->provider, [
                            'error' => $e->getMessage()
                        ]);
                        // En cas d'erreur, utiliser les valeurs par défaut
                        $detectionData['folders'] = [
                            'INBOX',
                            'Junk',
                            'Junk Email',
                            'Spam',
                            'Trash',
                            'Sent',
                            'Drafts'
                        ];
                    }
                } else {
                    // Pour les services IMAP génériques, récupérer les dossiers réels
                    $emailService = EmailServiceFactory::make($emailAccount);
                    
                    // Si c'est un service IMAP générique, essayer de récupérer les dossiers
                    if ($emailAccount->provider === 'imap') {
                        $testResult = $emailService->testConnection();
                        
                        if ((isset($testResult['success']) ? $testResult['success'] : ($testResult['status'] ?? false)) && isset($testResult['details']['folders'])) {
                            $detectionData['folders'] = $testResult['details']['folders'];
                            Log::info('Folders retrieved from IMAP server', [
                                'email' => $emailAccount->email,
                                'folders' => $detectionData['folders']
                            ]);
                        } else {
                            // Si on ne peut pas récupérer les dossiers, utiliser une liste par défaut
                            $detectionData['folders'] = [
                                'INBOX',
                                'Junk',
                                'Junk Email',
                                'Spam',
                                'Trash',
                                'Sent',
                                'Drafts'
                            ];
                            Log::warning('Could not retrieve folders from IMAP server, using defaults', [
                                'email' => $emailAccount->email,
                                'error' => $testResult['message'] ?? 'Unknown error'
                            ]);
                        }
                    } else {
                        // Pour les autres types de services, utiliser des dossiers par défaut
                        $detectionData['folders'] = [
                            'INBOX',
                            'Junk',
                            'Junk Email',
                            'Spam',
                            'Trash',
                            'Sent',
                            'Drafts'
                        ];
                    }
                }
            }
            
            // 2. Ne pas récupérer automatiquement les emails, on le fera via AJAX
            // L'utilisateur choisira le dossier à analyser
            
            // 3. Détecter l'anti-spam basé sur les dossiers et les en-têtes
            try {
                $detectionData['detected_antispam'] = $this->detectFromFoldersAndHeaders(
                    $detectionData['folders'], 
                    $detectionData['emails']
                );
            } catch (\Exception $e) {
                Log::warning('Error during antispam detection from folders/headers', [
                    'error' => $e->getMessage(),
                    'folders' => $detectionData['folders']
                ]);
                $detectionData['detected_antispam'] = null;
            }
            
            // 4. Suggérer un mapping de dossiers
            if ($detectionData['detected_antispam']) {
                try {
                    $detectionData['suggested_mapping'] = $this->suggestFolderMapping(
                        $detectionData['folders'], 
                        $detectionData['detected_antispam']['provider']
                    );
                } catch (\Exception $e) {
                    Log::warning('Error suggesting folder mapping', [
                        'error' => $e->getMessage(),
                        'provider' => $detectionData['detected_antispam']['provider'] ?? 'unknown'
                    ]);
                    $detectionData['suggested_mapping'] = null;
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Anti-spam detection failed', [
                'account_id' => $emailAccount->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        Log::info('Returning antispam configuration view', [
            'account_id' => $emailAccount->id,
            'email' => $emailAccount->email,
            'provider' => $emailAccount->provider,
            'folders_count' => count($detectionData['folders'] ?? []),
            'has_folders' => !empty($detectionData['folders']),
            'folders' => $detectionData['folders'] ?? []
        ]);

        return view('admin.email-accounts.configure-antispam', compact('emailAccount', 'detectionData'));
    }

    /**
     * Save anti-spam configuration
     */
    public function saveAntispamConfig(Request $request, EmailAccount $emailAccount)
    {
        $request->validate([
            'antispam_systems' => 'nullable|array',
            'antispam_systems.*' => 'exists:antispam_systems,id',
            'folder_mappings' => 'required|array',
            'folder_mappings.*.folder_type' => 'required|string',
            'folder_mappings.*.folder_name' => 'required|string',
            'folder_mappings.*.display_name' => 'nullable|string',
            'folder_mappings.*.is_additional_inbox' => 'boolean',
        ]);

        DB::beginTransaction();
        
        try {
            // Clear existing antispam associations
            $emailAccount->antispamSystems()->detach();
            
            // Add new antispam associations
            if (!empty($request->antispam_systems)) {
                foreach ($request->antispam_systems as $systemId) {
                    $emailAccount->antispamSystems()->attach($systemId, [
                        'detected_at' => now()
                    ]);
                }
            }
            
            // Clear existing folder mappings
            $emailAccount->folderMappings()->delete();
            
            // Add new folder mappings
            // Get the email_provider_id based on the account's provider
            $emailProviderId = DB::table('email_providers')
                ->where('name', $emailAccount->provider)
                ->value('id');
            
            if (!$emailProviderId) {
                // Fallback to imap provider if not found
                $emailProviderId = DB::table('email_providers')
                    ->where('name', 'imap')
                    ->value('id') ?? 4;
            }
            
            $sortOrder = 0;
            foreach ($request->folder_mappings as $mapping) {
                EmailFolderMapping::create([
                    'email_account_id' => $emailAccount->id,
                    'email_provider_id' => $emailProviderId,
                    'folder_type' => $mapping['folder_type'],
                    'folder_name' => $mapping['folder_name'],
                    'display_name' => $mapping['display_name'] ?? null,
                    'is_additional_inbox' => $mapping['is_additional_inbox'] ?? false,
                    'sort_order' => $sortOrder++,
                ]);
            }
            
            // Activate the account
            $emailAccount->update([
                'is_active' => true,
            ]);
            
            DB::commit();

            return redirect()
                ->route('admin.email-accounts.index')
                ->with('success', 'Configuration anti-spam enregistrée avec succès. Le compte est maintenant actif et pleinement opérationnel.');
                
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error saving antispam config', [
                'error' => $e->getMessage(),
                'account_id' => $emailAccount->id,
            ]);
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
        }
    }

    /**
     * Detect anti-spam configuration automatically
     */
    private function detectAntispam(EmailAccount $emailAccount)
    {
        try {
            $emailService = EmailServiceFactory::make($emailAccount);
            $testResult = $emailService->testConnection();
            
            if ((isset($testResult['success']) ? $testResult['success'] : ($testResult['status'] ?? false)) && isset($testResult['details']['folders'])) {
                $folders = $testResult['details']['folders'];
                
                // Détecter le provider anti-spam basé sur les dossiers
                $detectedProvider = $this->detectProviderFromFolders($folders);
                
                if ($detectedProvider) {
                    $emailAccount->update([
                        'detected_antispam' => [
                            'provider' => $detectedProvider['provider'],
                            'confidence' => $detectedProvider['confidence'],
                            'detected_folders' => $detectedProvider['folders'],
                        ]
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Anti-spam detection failed', [
                'account_id' => $emailAccount->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Detect anti-spam provider from folder names
     */
    private function detectProviderFromFolders(array $folders): ?array
    {
        $folderNames = array_map('strtolower', $folders);
        
        // Gmail
        if (in_array('[gmail]/spam', $folderNames) || in_array('spam', $folderNames)) {
            return [
                'provider' => 'gmail',
                'confidence' => 'high',
                'folders' => [
                    'inbox' => 'INBOX',
                    'spam' => in_array('[gmail]/spam', $folderNames) ? '[Gmail]/Spam' : 'Spam',
                ]
            ];
        }
        
        // Outlook/Microsoft
        if (in_array('junk email', $folderNames) || in_array('junk', $folderNames)) {
            return [
                'provider' => 'outlook',
                'confidence' => 'high',
                'folders' => [
                    'inbox' => 'Inbox',
                    'spam' => 'Junk Email',
                ]
            ];
        }
        
        // Yahoo
        if (in_array('bulk mail', $folderNames)) {
            return [
                'provider' => 'yahoo',
                'confidence' => 'high',
                'folders' => [
                    'inbox' => 'Inbox',
                    'spam' => 'Bulk Mail',
                ]
            ];
        }
        
        // SpamAssassin / cPanel
        if (in_array('spam', $folderNames)) {
            return [
                'provider' => 'spamassassin',
                'confidence' => 'medium',
                'folders' => [
                    'inbox' => 'INBOX',
                    'spam' => 'Spam',
                ]
            ];
        }
        
        return null;
    }
    
    /**
     * Analyze email headers to detect anti-spam systems
     */
    private function analyzeHeaders(string $headers): array
    {
        $detected = [];
        $evidence = [];
        
        // Ensure headers are properly encoded
        $headers = mb_convert_encoding($headers, 'UTF-8', 'UTF-8');
        $headerLines = explode("\n", $headers);
        
        foreach ($headerLines as $line) {
            $line = trim($line);
            
            // SpamAssassin
            if (stripos($line, 'X-Spam-') !== false || stripos($line, 'SpamAssassin') !== false) {
                $detected['spamassassin'] = true;
                $evidence['spamassassin'][] = $line;
            }
            
            // Rspamd
            if (stripos($line, 'X-Rspamd-') !== false || stripos($line, 'Rspamd') !== false) {
                $detected['rspamd'] = true;
                $evidence['rspamd'][] = $line;
            }
            
            // Amavis
            if (stripos($line, 'X-Amavis-') !== false || stripos($line, 'Amavis') !== false) {
                $detected['amavis'] = true;
                $evidence['amavis'][] = $line;
            }
            
            // Barracuda
            if (stripos($line, 'X-Barracuda-') !== false) {
                $detected['barracuda'] = true;
                $evidence['barracuda'][] = $line;
            }
            
            // Microsoft/Exchange
            if (stripos($line, 'X-MS-Exchange-') !== false || 
                stripos($line, 'X-Microsoft-Antispam') !== false ||
                stripos($line, 'X-Forefront-') !== false ||
                stripos($line, 'X-Exchange-Antispam') !== false) {
                $detected['microsoft'] = true;
                $evidence['microsoft'][] = $line;
            }
            
            // Gmail
            if (stripos($line, 'X-Gm-') !== false || 
                stripos($line, 'ARC-Authentication-Results: i=1; mx.google.com') !== false ||
                stripos($line, 'X-Google-') !== false) {
                $detected['gmail'] = true;
                $evidence['gmail'][] = $line;
            }
            
            // Yahoo
            if (stripos($line, 'X-YMail-') !== false || 
                stripos($line, 'X-Yahoo-') !== false ||
                stripos($line, 'X-YahooFilteredBulk') !== false) {
                $detected['yahoo'] = true;
                $evidence['yahoo'][] = $line;
            }
            
            // Postfix/Dovecot
            if (stripos($line, 'X-Postfix-') !== false || stripos($line, 'X-Dovecot-') !== false) {
                $detected['postfix'] = true;
                $evidence['postfix'][] = $line;
            }
            
            // cPanel/WHM
            if (stripos($line, 'X-cPanel-') !== false || stripos($line, 'X-WHM-') !== false) {
                $detected['cpanel'] = true;
                $evidence['cpanel'][] = $line;
            }
            
            // Mimecast
            if (stripos($line, 'X-Mimecast-') !== false) {
                $detected['mimecast'] = true;
                $evidence['mimecast'][] = $line;
            }
            
            // Proofpoint
            if (stripos($line, 'X-Proofpoint-') !== false) {
                $detected['proofpoint'] = true;
                $evidence['proofpoint'][] = $line;
            }
            
            // Symantec
            if (stripos($line, 'X-Symantec-') !== false) {
                $detected['symantec'] = true;
                $evidence['symantec'][] = $line;
            }
        }
        
        // Return both detection results and evidence
        return [
            'detected' => $detected,
            'evidence' => $evidence
        ];
    }
    
    /**
     * Detect anti-spam from folders and email headers
     */
    private function detectFromFoldersAndHeaders(array $folders, array $emails): ?array
    {
        // D'abord essayer la détection par dossiers
        $folderDetection = $this->detectProviderFromFolders($folders);
        
        // Ensuite analyser les en-têtes
        $headerDetection = [];
        foreach ($emails as $email) {
            if (isset($email['detected_antispam'])) {
                foreach ($email['detected_antispam'] as $provider => $detected) {
                    if ($detected) {
                        $headerDetection[$provider] = ($headerDetection[$provider] ?? 0) + 1;
                    }
                }
            }
        }
        
        // Combiner les résultats
        if ($folderDetection) {
            return $folderDetection;
        }
        
        // Si pas de détection par dossiers, utiliser les en-têtes
        if (!empty($headerDetection)) {
            // Prendre le plus fréquent
            arsort($headerDetection);
            $mostFrequent = array_key_first($headerDetection);
            
            return [
                'provider' => $mostFrequent,
                'confidence' => 'medium',
                'detected_by' => 'headers',
                'header_evidence' => $headerDetection,
            ];
        }
        
        return null;
    }
    
    /**
     * Suggest folder mapping based on provider
     */
    private function suggestFolderMapping(array $folders, string $provider): array
    {
        $folderNames = array_map('strtolower', $folders);
        $mapping = [];
        
        // Configuration spécifique pour Gmail avec tous les dossiers par défaut
        if ($provider === 'gmail') {
            // Configuration par défaut identique à pgaliegue33@gmail.com
            $mapping = [
                [
                    'folder_type' => 'inbox',
                    'folder_name' => 'INBOX',
                    'display_name' => 'Boîte de réception'
                ],
                [
                    'folder_type' => 'spam',
                    'folder_name' => 'SPAM',
                    'display_name' => 'Spam/Indésirables'
                ],
                [
                    'folder_type' => 'additional_inbox',
                    'folder_name' => 'INBOX (Promotions)',
                    'display_name' => 'Promotions'
                ],
                [
                    'folder_type' => 'additional_inbox',
                    'folder_name' => 'INBOX (Social)',
                    'display_name' => 'Social'
                ],
                [
                    'folder_type' => 'additional_inbox',
                    'folder_name' => 'INBOX (Updates)',
                    'display_name' => 'Updates'
                ],
                [
                    'folder_type' => 'additional_inbox',
                    'folder_name' => 'INBOX (Forums)',
                    'display_name' => 'Forums'
                ]
            ];
            
            return $mapping;
        }
        
        // Configuration par défaut pour les autres providers
        $defaultMapping = [
            'inbox' => 'INBOX',
            'spam' => null,
            'promotions' => null,
        ];
        
        // Rechercher la boîte de réception
        foreach ($folders as $folder) {
            $lower = strtolower($folder);
            if ($lower === 'inbox' || $lower === 'boîte de réception') {
                $defaultMapping['inbox'] = $folder;
                break;
            }
        }
        
        // Rechercher le dossier spam selon le provider
        switch ($provider) {
                
            case 'outlook':
            case 'microsoft':
                foreach ($folders as $folder) {
                    $lower = strtolower($folder);
                    if ($lower === 'junk email' || $lower === 'junk' || $lower === 'courrier indésirable') {
                        $mapping['spam'] = $folder;
                        break;
                    }
                }
                break;
                
            case 'yahoo':
                foreach ($folders as $folder) {
                    $lower = strtolower($folder);
                    if ($lower === 'bulk mail' || $lower === 'spam') {
                        $mapping['spam'] = $folder;
                        break;
                    }
                }
                break;
                
            default:
                // Recherche générique
                foreach ($folders as $folder) {
                    $lower = strtolower($folder);
                    if (in_array($lower, ['spam', 'junk', 'bulk', 'courrier indésirable', 'pourriel'])) {
                        $defaultMapping['spam'] = $folder;
                        break;
                    }
                }
        }
        
        // Convertir en format array pour cohérence avec Gmail
        $finalMapping = [];
        if ($defaultMapping['inbox']) {
            $finalMapping[] = [
                'folder_type' => 'inbox',
                'folder_name' => $defaultMapping['inbox'],
                'display_name' => 'Boîte de réception'
            ];
        }
        if ($defaultMapping['spam']) {
            $finalMapping[] = [
                'folder_type' => 'spam',
                'folder_name' => $defaultMapping['spam'],
                'display_name' => 'Spam/Indésirables'
            ];
        }
        if ($defaultMapping['promotions']) {
            $finalMapping[] = [
                'folder_type' => 'additional_inbox',
                'folder_name' => $defaultMapping['promotions'],
                'display_name' => 'Promotions'
            ];
        }
        
        return $finalMapping;
    }
    
    /**
     * Analyze emails from a specific folder via AJAX
     */
    public function analyzeFolder(Request $request, EmailAccount $emailAccount)
    {
        $request->validate([
            'folder' => 'required|string',
        ]);
        
        $folderName = $request->folder;
        $results = [
            'emails' => [],
            'detected_antispam' => [],
        ];
        
        try {
            $emailService = EmailServiceFactory::make($emailAccount);
            
            // Check if method exists
            if (!method_exists($emailService, 'getEmailsFromFolder')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cette méthode n\'est pas encore implémentée pour ce type de compte.',
                ], 200);
            }
            
            // Get emails from the specified folder
            $emails = $emailService->getEmailsFromFolder($folderName, 3); // Get 3 emails
            
            Log::info('Emails retrieved from folder', [
                'folder' => $folderName,
                'email_count' => count($emails),
                'provider' => $emailAccount->provider
            ]);
            
            foreach ($emails as $index => $email) {
                Log::info('Processing email', [
                    'index' => $index,
                    'has_id' => isset($email['id']),
                    'id' => $email['id'] ?? 'no-id'
                ]);
                
                if (isset($email['id'])) {
                    // Use headers directly from the email if available
                    $headers = $email['headers'] ?? null;
                    
                    Log::info('Using headers from email', [
                        'id' => $email['id'],
                        'has_headers' => !empty($headers),
                        'headers_length' => strlen($headers ?? '')
                    ]);
                    
                    // Process even if headers are empty
                    // Use HeaderAnalyzer for all active systems during detection phase
                    $analysisResult = HeaderAnalyzer::analyzeAll($headers ?: '');
                    $detectedSystems = $analysisResult['detected'];
                    $evidence = $analysisResult['evidence'];
                    
                    // Ensure proper UTF-8 encoding for all string fields
                    $results['emails'][] = [
                        'subject' => mb_convert_encoding($email['subject'] ?? 'Sans sujet', 'UTF-8', 'UTF-8'),
                        'date' => $email['date'] ?? null,
                        'folder' => $email['folder'] ?? $folderName,
                        'headers' => mb_convert_encoding($headers ?: 'No headers available', 'UTF-8', 'UTF-8'),
                        'detected_antispam' => $detectedSystems,
                        'evidence' => array_map(function($lines) {
                            return array_map(function($line) {
                                return mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                            }, $lines);
                        }, $evidence),
                    ];
                    
                    // Aggregate detected systems
                    foreach ($detectedSystems as $system => $detected) {
                        if ($detected) {
                            $results['detected_antispam'][$system] = ($results['detected_antispam'][$system] ?? 0) + 1;
                        }
                    }
                }
            }
            
            // Determine the most likely anti-spam system
            if (!empty($results['detected_antispam'])) {
                arsort($results['detected_antispam']);
                $mostLikely = array_key_first($results['detected_antispam']);
                $results['suggested_provider'] = $mostLikely;
            }
            
            Log::info('Returning analysis results', [
                'email_count' => count($results['emails']),
                'detected_systems' => array_keys($results['detected_antispam'] ?? [])
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error analyzing folder', [
                'account_id' => $emailAccount->id,
                'folder' => $folderName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 200); // Toujours retourner 200
        }
    }
    
    /**
     * Update password for email account
     */
    public function updatePassword(Request $request, EmailAccount $emailAccount)
    {
        $request->validate([
            'password' => 'required|string|min:6',
            'imap_password' => 'nullable|string|min:6',
        ]);
        
        try {
            $updateData = [
                'password' => encrypt($request->password),
                'connection_status' => 'unknown', // Reset connection status
                'connection_error' => null,
            ];
            
            // For generic IMAP, allow separate IMAP password
            if ($emailAccount->provider === 'imap' && $request->filled('imap_password')) {
                $updateData['imap_password'] = encrypt($request->imap_password);
            }
            
            $emailAccount->update($updateData);
            
            return redirect()
                ->route('admin.email-accounts.edit', $emailAccount)
                ->with('success', 'Mot de passe mis à jour avec succès. Testez la connexion pour vérifier.');
                
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.email-accounts.edit', $emailAccount)
                ->with('error', 'Erreur lors de la mise à jour du mot de passe: ' . $e->getMessage());
        }
    }

    /**
     * Toggle account active status
     */
    public function toggleStatus(EmailAccount $emailAccount)
    {
        try {
            $emailAccount->is_active = !$emailAccount->is_active;
            $emailAccount->save();

            $status = $emailAccount->is_active ? 'activé' : 'désactivé';
            return redirect()
                ->route('admin.email-accounts.index')
                ->with('success', "Le compte {$emailAccount->email} a été {$status} avec succès.");
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.email-accounts.index')
                ->with('error', 'Erreur lors du changement de statut: ' . $e->getMessage());
        }
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderController extends Controller
{
    /**
     * Afficher la liste des providers
     */
    public function index(Request $request)
    {
        $query = EmailProvider::query();

        // Filtrer par type
        if ($request->filled('provider_type')) {
            $query->where('provider_type', $request->provider_type);
        }

        // Filtrer par OAuth
        if ($request->filled('oauth')) {
            $query->where('supports_oauth', $request->oauth);
        }

        // Filtrer par statut
        if ($request->filled('active')) {
            $query->where('is_active', $request->active);
        }

        // Recherche
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('imap_host', 'like', "%{$search}%");
            });
        }

        $providers = $query->orderBy('display_name')->paginate(20);

        return view('admin.providers.index', compact('providers'));
    }

    /**
     * Formulaire de création
     */
    public function create()
    {
        return view('admin.providers.create');
    }

    /**
     * Enregistrer un nouveau provider
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:email_providers',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'provider_type' => 'required|in:b2c,b2b,custom,discontinued',
            'imap_host' => 'nullable|string|max:255',
            'imap_port' => 'nullable|integer',
            'imap_encryption' => 'nullable|in:ssl,tls,none',
            'supports_oauth' => 'boolean',
            'oauth_provider' => 'nullable|string|max:50',
            'requires_app_password' => 'boolean',
            'instructions' => 'nullable|string',
            'notes' => 'nullable|string',
            'domains' => 'nullable|string',
            'mx_patterns' => 'nullable|string',
        ]);

        // Convertir les chaînes en tableaux JSON
        if ($request->filled('domains')) {
            $validated['domains'] = array_map('trim', explode("\n", $request->domains));
        }
        if ($request->filled('mx_patterns')) {
            $validated['mx_patterns'] = array_map('trim', explode("\n", $request->mx_patterns));
        }

        EmailProvider::create($validated);

        return redirect()->route('admin.providers.index')
            ->with('success', 'Fournisseur créé avec succès');
    }

    /**
     * Formulaire d'édition
     */
    public function edit(EmailProvider $provider)
    {
        return view('admin.providers.edit', compact('provider'));
    }

    /**
     * Mettre à jour un provider
     */
    public function update(Request $request, EmailProvider $provider)
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'provider_type' => 'required|in:b2c,b2b,custom,discontinued',
            'imap_host' => 'nullable|string|max:255',
            'imap_port' => 'nullable|integer',
            'imap_encryption' => 'nullable|in:ssl,tls,none',
            'validate_cert' => 'boolean',
            'supports_oauth' => 'boolean',
            'oauth_provider' => 'nullable|string|max:50',
            'requires_app_password' => 'boolean',
            'max_connections_per_hour' => 'nullable|integer|min:1|max:1000',
            'max_checks_per_connection' => 'nullable|integer|min:1|max:1000',
            'connection_backoff_minutes' => 'nullable|integer|min:1|max:1440',
            'supports_idle' => 'boolean',
            'instructions' => 'nullable|string',
            'notes' => 'nullable|string',
            'domains' => 'nullable|string',
            'mx_patterns' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Convertir les chaînes en tableaux JSON
        if ($request->filled('domains')) {
            $validated['domains'] = array_map('trim', explode("\n", $request->domains));
        }
        if ($request->filled('mx_patterns')) {
            $validated['mx_patterns'] = array_map('trim', explode("\n", $request->mx_patterns));
        }
        
        // Nettoyer les intervalles de vérification
        if ($request->has('check_intervals')) {
            $validated['check_intervals'] = array_values(array_filter($request->check_intervals, function($interval) {
                return !empty($interval['max_age_minutes']) && !empty($interval['interval_minutes']);
            }));
        }

        $provider->update($validated);

        return redirect()->route('admin.providers.index')
            ->with('success', 'Fournisseur mis à jour avec succès');
    }

    /**
     * Supprimer un provider
     */
    public function destroy(EmailProvider $provider)
    {
        // Vérifier s'il y a des comptes email associés
        if ($provider->emailAccounts()->exists()) {
            return redirect()->route('admin.providers.index')
                ->with('error', 'Impossible de supprimer ce fournisseur car des comptes email y sont associés');
        }

        $provider->delete();

        return redirect()->route('admin.providers.index')
            ->with('success', 'Fournisseur supprimé avec succès');
    }

    /**
     * Tester la configuration IMAP/SMTP d'un provider
     * 
     * Ce test vérifie :
     * 1. La connectivité au serveur IMAP (port accessible)
     * 2. La bannière IMAP (réponse du serveur)
     * 3. La connectivité au serveur SMTP si configuré
     * 4. Les informations SSL/TLS si applicable
     */
    public function test(EmailProvider $provider)
    {
        if (!$provider->imap_host) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune configuration IMAP pour ce fournisseur'
            ]);
        }

        $results = [];
        $messages = [];
        $overallSuccess = true;

        // Test IMAP
        try {
            $imapProtocol = ($provider->imap_encryption === 'ssl') ? 'ssl://' : '';
            $imapAddress = $imapProtocol . $provider->imap_host . ':' . $provider->imap_port;
            
            // Contexte pour ignorer les erreurs de certificat si nécessaire
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => $provider->validate_cert,
                    'verify_peer_name' => $provider->validate_cert,
                    'allow_self_signed' => !$provider->validate_cert
                ]
            ]);
            
            $connection = @stream_socket_client(
                $imapAddress,
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($connection) {
                // Lire la bannière du serveur IMAP
                stream_set_timeout($connection, 2);
                $banner = fgets($connection, 1024);
                
                $messages[] = "✅ IMAP: Connexion réussie à {$provider->imap_host}:{$provider->imap_port}";
                if ($banner) {
                    // Nettoyer et extraire le nom du serveur de la bannière
                    $cleanBanner = trim(str_replace(['* OK', '[', ']'], '', $banner));
                    $messages[] = "   Serveur: " . substr($cleanBanner, 0, 50);
                }
                
                fclose($connection);
            } else {
                $overallSuccess = false;
                $messages[] = "❌ IMAP: Impossible de se connecter à {$provider->imap_host}:{$provider->imap_port}";
                if ($errstr) {
                    $messages[] = "   Erreur: $errstr";
                }
            }
        } catch (\Exception $e) {
            $overallSuccess = false;
            $messages[] = "❌ IMAP: Erreur - " . $e->getMessage();
        }

        // Test SMTP si configuré
        if ($provider->smtp_host) {
            try {
                $smtpProtocol = ($provider->smtp_encryption === 'ssl') ? 'ssl://' : '';
                $smtpAddress = $smtpProtocol . $provider->smtp_host . ':' . $provider->smtp_port;
                
                $connection = @stream_socket_client(
                    $smtpAddress,
                    $errno,
                    $errstr,
                    10,
                    STREAM_CLIENT_CONNECT,
                    $context ?? null
                );

                if ($connection) {
                    // Lire la bannière SMTP
                    stream_set_timeout($connection, 2);
                    $banner = fgets($connection, 1024);
                    
                    $messages[] = "✅ SMTP: Connexion réussie à {$provider->smtp_host}:{$provider->smtp_port}";
                    if ($banner && strpos($banner, '220') === 0) {
                        $messages[] = "   Serveur prêt";
                    }
                    
                    fclose($connection);
                } else {
                    $messages[] = "⚠️ SMTP: Impossible de se connecter à {$provider->smtp_host}:{$provider->smtp_port}";
                }
            } catch (\Exception $e) {
                $messages[] = "⚠️ SMTP: Erreur - " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => $overallSuccess,
            'message' => implode("\n", $messages)
        ]);
    }

    /**
     * Réinitialiser les providers avec les valeurs par défaut
     */
    public function reset()
    {
        DB::transaction(function () {
            // Supprimer les providers existants (sauf ceux avec des comptes)
            EmailProvider::whereDoesntHave('emailAccounts')->delete();

            // Recréer les providers par défaut
            $defaults = [
                [
                    'name' => 'gmail',
                    'display_name' => 'Gmail',
                    'provider_type' => 'b2c',
                    'imap_host' => 'imap.gmail.com',
                    'imap_port' => 993,
                    'imap_encryption' => 'ssl',
                    'smtp_host' => 'smtp.gmail.com',
                    'smtp_port' => 587,
                    'smtp_encryption' => 'tls',
                    'supports_oauth' => true,
                    'oauth_provider' => 'google',
                    'requires_app_password' => true,
                    'domains' => ['gmail.com', 'googlemail.com'],
                    'mx_patterns' => ['google.com', 'googlemail.com'],
                ],
                [
                    'name' => 'outlook',
                    'display_name' => 'Outlook / Hotmail',
                    'provider_type' => 'b2c',
                    'imap_host' => 'outlook.office365.com',
                    'imap_port' => 993,
                    'imap_encryption' => 'ssl',
                    'smtp_host' => 'smtp.office365.com',
                    'smtp_port' => 587,
                    'smtp_encryption' => 'tls',
                    'supports_oauth' => true,
                    'oauth_provider' => 'microsoft',
                    'requires_app_password' => false,
                    'domains' => ['outlook.com', 'hotmail.com', 'live.com', 'msn.com'],
                    'mx_patterns' => ['outlook.com', 'hotmail.com'],
                ],
                [
                    'name' => 'yahoo',
                    'display_name' => 'Yahoo Mail',
                    'provider_type' => 'b2c',
                    'imap_host' => 'imap.mail.yahoo.com',
                    'imap_port' => 993,
                    'imap_encryption' => 'ssl',
                    'smtp_host' => 'smtp.mail.yahoo.com',
                    'smtp_port' => 587,
                    'smtp_encryption' => 'tls',
                    'supports_oauth' => false,
                    'requires_app_password' => true,
                    'domains' => ['yahoo.com', 'yahoo.fr', 'ymail.com'],
                    'mx_patterns' => ['yahoo.com', 'yahoodns.net'],
                ],
            ];

            foreach ($defaults as $provider) {
                EmailProvider::firstOrCreate(
                    ['name' => $provider['name']],
                    $provider
                );
            }
        });

        return response()->json(['success' => true]);
    }
}
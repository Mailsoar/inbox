<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ImapProviderController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailProvider::query();

        // Filtres
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('display_name', 'like', '%' . $request->search . '%')
                  ->orWhere('name', 'like', '%' . $request->search . '%')
                  ->orWhere('imap_host', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $providers = $query->orderBy('display_name')
            ->paginate(20)
            ->withQueryString();

        $totalProviders = EmailProvider::count();
        $activeProviders = EmailProvider::where('is_active', true)->count();

        return view('admin.imap-providers.index', compact(
            'providers',
            'totalProviders',
            'activeProviders'
        ));
    }

    public function create()
    {
        return view('admin.imap-providers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:imap_providers,name',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'required|integer|min:1|max:65535',
            'encryption' => 'required|in:ssl,tls,none',
            'validate_cert' => 'boolean',
            'common_domains' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data = [
            'name' => $request->name,
            'display_name' => $request->display_name,
            'description' => $request->description,
            'imap_host' => $request->imap_host,
            'imap_port' => $request->imap_port,
            'encryption' => $request->encryption,
            'validate_cert' => $request->boolean('validate_cert'),
            'is_active' => $request->boolean('is_active'),
        ];

        // Parse common domains
        if ($request->filled('common_domains')) {
            $domains = array_map('trim', explode(',', $request->common_domains));
            $domains = array_filter($domains); // Remove empty values
            $data['common_domains'] = $domains;
        } else {
            $data['common_domains'] = [];
        }

        try {
            $provider = EmailProvider::create($data);

            return redirect()
                ->route('admin.imap-providers.index')
                ->with('success', 'Fournisseur IMAP créé avec succès.');
        } catch (\Exception $e) {
            Log::error('Error creating IMAP provider', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Erreur lors de la création : ' . $e->getMessage());
        }
    }

    public function show(EmailProvider $imapProvider)
    {
        $emailAccounts = $imapProvider->emailAccounts()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $stats = [
            'total_accounts' => $imapProvider->emailAccounts()->count(),
            'active_accounts' => $imapProvider->emailAccounts()->where('is_active', true)->count(),
        ];

        return view('admin.imap-providers.show', compact('imapProvider', 'emailAccounts', 'stats'));
    }

    public function edit(EmailProvider $imapProvider)
    {
        return view('admin.imap-providers.edit', compact('imapProvider'));
    }

    public function update(Request $request, EmailProvider $imapProvider)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:imap_providers,name,' . $imapProvider->id,
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'required|integer|min:1|max:65535',
            'encryption' => 'required|in:ssl,tls,none',
            'validate_cert' => 'boolean',
            'common_domains' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data = [
            'name' => $request->name,
            'display_name' => $request->display_name,
            'description' => $request->description,
            'imap_host' => $request->imap_host,
            'imap_port' => $request->imap_port,
            'encryption' => $request->encryption,
            'validate_cert' => $request->boolean('validate_cert'),
            'is_active' => $request->boolean('is_active'),
        ];

        // Parse common domains
        if ($request->filled('common_domains')) {
            $domains = array_map('trim', explode(',', $request->common_domains));
            $domains = array_filter($domains); // Remove empty values
            $data['common_domains'] = $domains;
        } else {
            $data['common_domains'] = [];
        }

        try {
            $imapProvider->update($data);

            return redirect()
                ->route('admin.imap-providers.index')
                ->with('success', 'Fournisseur IMAP mis à jour avec succès.');
        } catch (\Exception $e) {
            Log::error('Error updating IMAP provider', [
                'error' => $e->getMessage(),
                'provider_id' => $imapProvider->id,
                'data' => $data
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }
    }

    public function destroy(EmailProvider $imapProvider)
    {
        // Vérifier qu'il n'y a pas de comptes associés
        $accountCount = $imapProvider->emailAccounts()->count();

        if ($accountCount > 0) {
            return redirect()
                ->route('admin.imap-providers.index')
                ->with('error', "Impossible de supprimer ce fournisseur car {$accountCount} compte(s) email l'utilisent.");
        }

        // Ne pas supprimer le provider custom
        if ($imapProvider->isProtected()) {
            return redirect()
                ->route('admin.imap-providers.index')
                ->with('error', 'Le fournisseur "Configuration personnalisée" ne peut pas être supprimé.');
        }

        try {
            $imapProvider->delete();

            return redirect()
                ->route('admin.imap-providers.index')
                ->with('success', 'Fournisseur IMAP supprimé avec succès.');
        } catch (\Exception $e) {
            Log::error('Error deleting IMAP provider', [
                'error' => $e->getMessage(),
                'provider_id' => $imapProvider->id,
            ]);

            return redirect()
                ->route('admin.imap-providers.index')
                ->with('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }
    }

    /**
     * Test the IMAP configuration for a provider
     */
    public function testConfiguration(Request $request, EmailProvider $imapProvider)
    {
        $request->validate([
            'test_email' => 'required|email',
            'test_password' => 'required|string',
        ]);

        try {
            // Get IMAP configuration from provider
            $config = $imapProvider->getImapConfig();
            
            if (empty($config['host'])) {
                throw new \Exception('Host IMAP non configuré');
            }

            // Create IMAP client configuration
            $clientConfig = [
                'host' => $config['host'],
                'port' => $config['port'],
                'encryption' => $config['encryption'],
                'validate_cert' => $config['validate_cert'] ?? true,
                'username' => $request->test_email,
                'password' => $request->test_password,
                'protocol' => 'imap',
                'authentication' => 'NORMAL'
            ];

            // Test real IMAP connection
            $cm = new \Webklex\PHPIMAP\ClientManager();
            $client = $cm->make($clientConfig);
            
            // Try to connect
            $client->connect();
            
            // If connection successful, get folder count
            $folders = $client->getFolders();
            $folderCount = count($folders);
            
            // Get folder names
            $folderNames = [];
            foreach ($folders as $folder) {
                $folderNames[] = $folder->name;
                // Include children if any
                if ($folder->hasChildren()) {
                    try {
                        $children = $folder->getChildren();
                        foreach ($children as $child) {
                            $folderNames[] = $folder->name . '/' . $child->name;
                        }
                    } catch (\Exception $e) {
                        // Skip if can't get children
                    }
                }
            }
            
            // Disconnect
            $client->disconnect();

            return response()->json([
                'success' => true,
                'message' => "Connexion IMAP réussie ! {$folderCount} dossier(s) trouvé(s).",
                'details' => [
                    'folders_count' => $folderCount,
                    'folders' => array_slice($folderNames, 0, 10), // Show max 10 folders
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'encryption' => $config['encryption']
                ]
            ]);

        } catch (\Webklex\PHPIMAP\Exceptions\AuthFailedException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Échec de l\'authentification. Vérifiez l\'email et le mot de passe.'
            ]);
        } catch (\Webklex\PHPIMAP\Exceptions\ConnectionFailedException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de se connecter au serveur IMAP. Vérifiez l\'hôte et le port.'
            ]);
        } catch (\Exception $e) {
            Log::error('IMAP provider test failed', [
                'provider_id' => $imapProvider->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get providers as JSON for API
     */
    public function api()
    {
        $providers = EmailProvider::active()->ordered()->get();
        return response()->json($providers);
    }

    /**
     * Reset providers to factory defaults
     */
    public function reset()
    {
        DB::beginTransaction();
        
        try {
            // Default provider configurations
            $defaultProviders = [
                [
                    'name' => 'laposte',
                    'display_name' => 'La Poste',
                    'description' => 'Messagerie La Poste française',
                    'imap_host' => 'imap.laposte.net',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => ['laposte.net'],
                    'is_active' => true,
                ],
                [
                    'name' => 'orange',
                    'display_name' => 'Orange',
                    'description' => 'Messagerie Orange France',
                    'imap_host' => 'imap.orange.fr',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => ['orange.fr', 'wanadoo.fr'],
                    'is_active' => true,
                ],
                [
                    'name' => 'sfr',
                    'display_name' => 'SFR',
                    'description' => 'Messagerie SFR',
                    'imap_host' => 'imap.sfr.fr',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => ['sfr.fr', 'neuf.fr', 'cegetel.net'],
                    'is_active' => true,
                ],
                [
                    'name' => 'free',
                    'display_name' => 'Free',
                    'description' => 'Messagerie Free',
                    'imap_host' => 'imap.free.fr',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => ['free.fr'],
                    'is_active' => true,
                ],
                [
                    'name' => 'zoho',
                    'display_name' => 'Zoho Mail',
                    'description' => 'Zoho Mail service',
                    'imap_host' => 'imap.zoho.com',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => ['zoho.com', 'zoho.eu'],
                    'is_active' => true,
                ],
                [
                    'name' => 'webde',
                    'display_name' => 'Web.de',
                    'description' => 'Web.de German email service',
                    'imap_host' => 'imap.web.de',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => ['web.de'],
                    'is_active' => true,
                ],
                [
                    'name' => 'gmx',
                    'display_name' => 'GMX',
                    'description' => 'GMX email service',
                    'imap_host' => 'imap.gmx.net',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => ['gmx.com', 'gmx.de', 'gmx.net'],
                    'is_active' => true,
                ],
                [
                    'name' => 'amazon_workmail',
                    'display_name' => 'Amazon WorkMail',
                    'description' => 'Amazon WorkMail service',
                    'imap_host' => 'imap.mail.us-east-1.awsapps.com',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => [],
                    'is_active' => true,
                ],
                [
                    'name' => 'apple_icloud',
                    'display_name' => 'Apple iCloud',
                    'description' => 'Apple iCloud Mail',
                    'imap_host' => 'imap.mail.me.com',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => ['icloud.com', 'me.com', 'mac.com'],
                    'is_active' => true,
                ],
                [
                    'name' => 'ionos',
                    'display_name' => 'IONOS',
                    'description' => 'IONOS email hosting',
                    'imap_host' => 'imap.ionos.fr',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => [],
                    'is_active' => true,
                ],
                [
                    'name' => 'ovh',
                    'display_name' => 'OVH',
                    'description' => 'OVH email hosting',
                    'imap_host' => 'ssl0.ovh.net',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => [],
                    'is_active' => true,
                ],
                [
                    'name' => 'gandi',
                    'display_name' => 'Gandi',
                    'description' => 'Gandi email hosting',
                    'imap_host' => 'mail.gandi.net',
                    'imap_port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => true,
                    'common_domains' => [],
                    'is_active' => true,
                ],
            ];

            // Disable non-default providers
            EmailProvider::whereNotIn('name', array_merge(array_column($defaultProviders, 'name'), ['custom']))
                ->update(['is_active' => false]);

            // Update or create default providers
            foreach ($defaultProviders as $provider) {
                EmailProvider::updateOrCreate(
                    ['name' => $provider['name']],
                    $provider
                );
            }

            DB::commit();

            return redirect()
                ->route('admin.imap-providers.index')
                ->with('success', 'Les fournisseurs IMAP ont été réinitialisés avec succès aux valeurs d\'usine.');
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error resetting IMAP providers', [
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->route('admin.imap-providers.index')
                ->with('error', 'Erreur lors de la réinitialisation : ' . $e->getMessage());
        }
    }
}
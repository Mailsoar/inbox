<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailProvider;
use App\Models\EmailProviderPattern;
use App\Services\MxDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailProviderController extends Controller
{
    protected $mxService;

    public function __construct(MxDetectionService $mxService)
    {
        $this->mxService = $mxService;
    }

    /**
     * Afficher la liste des providers
     */
    public function index(Request $request)
    {
        $query = EmailProvider::withCount('patterns');

        // Filtrer par type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filtrer par statut
        if ($request->filled('status')) {
            if ($request->status === 'valid') {
                $query->valid();
            } else {
                $query->blocked();
            }
        }

        // Recherche
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhereHas('patterns', function ($q2) use ($search) {
                        $q2->where('pattern', 'like', "%{$search}%");
                    });
            });
        }

        $providers = $query->orderBy('detection_priority')
            ->orderBy('display_name')
            ->paginate(20);

        // Statistiques
        $stats = [
            'total' => EmailProvider::count(),
            'valid' => EmailProvider::valid()->count(),
            'blocked' => EmailProvider::blocked()->count(),
            'by_type' => EmailProvider::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray()
        ];

        return view('admin.email-providers.index', compact('providers', 'stats'));
    }

    /**
     * Afficher le formulaire de création
     */
    public function create()
    {
        return view('admin.email-providers.create');
    }

    /**
     * Créer un nouveau provider
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:email_providers,name',
            'display_name' => 'required|string|max:100',
            'type' => 'required|in:b2c,b2b,antispam,temporary,blacklisted,discontinued',
            'is_valid' => 'boolean',
            'detection_priority' => 'required|integer|min:1|max:999',
            'notes' => 'nullable|string',
            'domains' => 'nullable|string',
            'mx_patterns' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Créer le provider
            $provider = EmailProvider::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'type' => $validated['type'],
                'is_valid' => $validated['is_valid'] ?? true,
                'detection_priority' => $validated['detection_priority'],
                'notes' => $validated['notes']
            ]);

            // Ajouter les domaines
            if (!empty($validated['domains'])) {
                $domains = array_filter(array_map('trim', explode("\n", $validated['domains'])));
                foreach ($domains as $domain) {
                    if (!empty($domain)) {
                        EmailProviderPattern::create([
                            'provider_id' => $provider->id,
                            'pattern' => strtolower($domain),
                            'pattern_type' => 'domain'
                        ]);
                    }
                }
            }

            // Ajouter les patterns MX
            if (!empty($validated['mx_patterns'])) {
                $mxPatterns = array_filter(array_map('trim', explode("\n", $validated['mx_patterns'])));
                foreach ($mxPatterns as $pattern) {
                    if (!empty($pattern)) {
                        EmailProviderPattern::create([
                            'provider_id' => $provider->id,
                            'pattern' => strtolower($pattern),
                            'pattern_type' => 'mx'
                        ]);
                    }
                }
                
                // Aussi stocker dans le champ JSON pour compatibilité
                $provider->update(['mx_patterns' => $mxPatterns]);
            }

            DB::commit();
            return redirect()->route('admin.email-providers.index')
                ->with('success', "Provider '{$provider->display_name}' créé avec succès");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur lors de la création : ' . $e->getMessage());
        }
    }

    /**
     * Afficher les détails d'un provider
     */
    public function show(EmailProvider $emailProvider)
    {
        $emailProvider->load('patterns');
        
        // Exemples d'emails pour test
        $testEmails = [];
        if ($emailProvider->domainPatterns->count() > 0) {
            foreach ($emailProvider->domainPatterns->take(3) as $pattern) {
                $testEmails[] = 'test@' . $pattern->pattern;
            }
        }

        return view('admin.email-providers.show', compact('emailProvider', 'testEmails'));
    }

    /**
     * Afficher le formulaire d'édition
     */
    public function edit(EmailProvider $emailProvider)
    {
        $emailProvider->load('patterns');
        
        // Préparer les domaines et MX pour l'affichage
        $domains = $emailProvider->domainPatterns->pluck('pattern')->implode("\n");
        $mxPatterns = $emailProvider->mxPatterns->pluck('pattern')->implode("\n");

        return view('admin.email-providers.edit', compact('emailProvider', 'domains', 'mxPatterns'));
    }

    /**
     * Mettre à jour un provider
     */
    public function update(Request $request, EmailProvider $emailProvider)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:email_providers,name,' . $emailProvider->id,
            'display_name' => 'required|string|max:100',
            'type' => 'required|in:b2c,b2b,antispam,temporary,blacklisted,discontinued',
            'is_valid' => 'boolean',
            'detection_priority' => 'required|integer|min:1|max:999',
            'notes' => 'nullable|string',
            'domains' => 'nullable|string',
            'mx_patterns' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Mettre à jour le provider
            $emailProvider->update([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'type' => $validated['type'],
                'is_valid' => $validated['is_valid'] ?? true,
                'detection_priority' => $validated['detection_priority'],
                'notes' => $validated['notes']
            ]);

            // Supprimer les anciens patterns
            $emailProvider->patterns()->delete();

            // Ajouter les nouveaux domaines
            if (!empty($validated['domains'])) {
                $domains = array_filter(array_map('trim', explode("\n", $validated['domains'])));
                foreach ($domains as $domain) {
                    if (!empty($domain)) {
                        EmailProviderPattern::create([
                            'provider_id' => $emailProvider->id,
                            'pattern' => strtolower($domain),
                            'pattern_type' => 'domain'
                        ]);
                    }
                }
            }

            // Ajouter les nouveaux patterns MX
            if (!empty($validated['mx_patterns'])) {
                $mxPatterns = array_filter(array_map('trim', explode("\n", $validated['mx_patterns'])));
                foreach ($mxPatterns as $pattern) {
                    if (!empty($pattern)) {
                        EmailProviderPattern::create([
                            'provider_id' => $emailProvider->id,
                            'pattern' => strtolower($pattern),
                            'pattern_type' => 'mx'
                        ]);
                    }
                }
                
                // Aussi stocker dans le champ JSON
                $emailProvider->update(['mx_patterns' => $mxPatterns]);
            } else {
                $emailProvider->update(['mx_patterns' => null]);
            }

            DB::commit();
            return redirect()->route('admin.email-providers.show', $emailProvider)
                ->with('success', "Provider '{$emailProvider->display_name}' mis à jour avec succès");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }
    }

    /**
     * Supprimer un provider
     */
    public function destroy(EmailProvider $emailProvider)
    {
        DB::beginTransaction();
        try {
            // Supprimer les patterns associés
            $emailProvider->patterns()->delete();
            
            // Supprimer le provider
            $name = $emailProvider->display_name;
            $emailProvider->delete();
            
            DB::commit();
            return redirect()->route('admin.email-providers.index')
                ->with('success', "Provider '{$name}' supprimé avec succès");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }
    }

    /**
     * Tester la détection d'un email
     */
    public function test(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = $request->email;
        $result = $this->mxService->analyzeEmail($email);

        return response()->json([
            'email' => $email,
            'result' => $result
        ]);
    }

    /**
     * Réinitialiser les providers par défaut
     */
    public function reset(Request $request)
    {
        if (!$request->has('confirm')) {
            return back()->with('error', 'Veuillez confirmer la réinitialisation');
        }

        DB::beginTransaction();
        try {
            // Supprimer tous les providers existants
            EmailProviderPattern::truncate();
            EmailProvider::truncate();
            
            // Réimporter les providers par défaut
            $importScript = base_path('scripts/import_email_providers.php');
            if (file_exists($importScript)) {
                ob_start();
                include $importScript;
                ob_end_clean();
            }
            
            DB::commit();
            return redirect()->route('admin.email-providers.index')
                ->with('success', 'Providers réinitialisés avec succès');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erreur lors de la réinitialisation : ' . $e->getMessage());
        }
    }
}
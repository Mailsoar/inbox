<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FilterRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FilterRuleController extends Controller
{
    /**
     * Afficher la liste des règles
     */
    public function index(Request $request)
    {
        $query = FilterRule::query();

        // Filtres
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('action') && $request->action) {
            $query->where('action', $request->action);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active == '1');
        }

        // Recherche
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('value', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $rules = $query->orderBy('type')
                      ->orderBy('created_at', 'desc')
                      ->paginate(20);

        return view('admin.filter-rules.index', compact('rules'));
    }

    /**
     * Afficher le formulaire de création
     */
    public function create()
    {
        return view('admin.filter-rules.create');
    }

    /**
     * Enregistrer une nouvelle règle
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:ip,domain,mx,email_pattern',
            'value' => 'required|string|max:255',
            'action' => 'required|in:block,allow',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'options' => 'nullable|array'
        ]);

        // Validation spécifique selon le type
        switch ($validated['type']) {
            case 'ip':
                // Valider IP ou CIDR
                if (!filter_var($validated['value'], FILTER_VALIDATE_IP) && 
                    !preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $validated['value']) &&
                    !preg_match('/^\d{1,3}\.\d{1,3}\.\*\.\*$/', $validated['value'])) {
                    return back()->withErrors(['value' => 'Format IP invalide'])->withInput();
                }
                break;
                
            case 'domain':
                // Valider domaine
                if (!preg_match('/^(\*\.)?[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $validated['value'])) {
                    return back()->withErrors(['value' => 'Format de domaine invalide'])->withInput();
                }
                break;
        }

        // Pour les options spéciales de normalisation
        if ($validated['type'] === 'email_pattern' && $validated['value'] === 'normalization_settings') {
            $validated['options'] = [
                'normalize_gmail_dots' => $request->input('normalize_gmail_dots', false),
                'normalize_plus_aliases' => $request->input('normalize_plus_aliases', false),
                'gmail_domains' => array_filter(explode(',', $request->input('gmail_domains', 'gmail.com,googlemail.com'))),
                'outlook_domains' => array_filter(explode(',', $request->input('outlook_domains', 'outlook.com,hotmail.com,live.com,msn.com')))
            ];
        }

        FilterRule::create($validated);

        return redirect()->route('admin.filter-rules.index')
                        ->with('success', 'Règle créée avec succès');
    }

    /**
     * Afficher le formulaire d'édition
     */
    public function edit(FilterRule $filterRule)
    {
        return view('admin.filter-rules.edit', compact('filterRule'));
    }

    /**
     * Mettre à jour une règle
     */
    public function update(Request $request, FilterRule $filterRule)
    {
        $validated = $request->validate([
            'type' => 'required|in:ip,domain,mx,email_pattern',
            'value' => 'required|string|max:255',
            'action' => 'required|in:block,allow',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'options' => 'nullable|array'
        ]);

        // Pour les options spéciales de normalisation
        if ($validated['type'] === 'email_pattern' && $validated['value'] === 'normalization_settings') {
            $validated['options'] = [
                'normalize_gmail_dots' => $request->input('normalize_gmail_dots', false),
                'normalize_plus_aliases' => $request->input('normalize_plus_aliases', false),
                'gmail_domains' => array_filter(explode(',', $request->input('gmail_domains', ''))),
                'outlook_domains' => array_filter(explode(',', $request->input('outlook_domains', '')))
            ];
        }

        $filterRule->update($validated);

        return redirect()->route('admin.filter-rules.index')
                        ->with('success', 'Règle mise à jour avec succès');
    }

    /**
     * Supprimer une règle
     */
    public function destroy(FilterRule $filterRule)
    {
        $filterRule->delete();

        return redirect()->route('admin.filter-rules.index')
                        ->with('success', 'Règle supprimée avec succès');
    }

    /**
     * Activer/Désactiver une règle
     */
    public function toggle(FilterRule $filterRule)
    {
        $filterRule->update([
            'is_active' => !$filterRule->is_active
        ]);

        return redirect()->back()
                        ->with('success', 'Statut de la règle mis à jour');
    }

    /**
     * Tester une règle
     */
    public function test(Request $request)
    {
        $validated = $request->validate([
            'test_value' => 'required|string',
            'test_type' => 'required|in:ip,email,domain'
        ]);

        $result = [];

        switch ($validated['test_type']) {
            case 'ip':
                $result['blocked'] = FilterRule::isIpBlocked($validated['test_value']);
                break;
                
            case 'domain':
                $result['blocked'] = FilterRule::isDomainBlocked($validated['test_value']);
                break;
                
            case 'email':
                $domain = substr($validated['test_value'], strpos($validated['test_value'], '@') + 1);
                $result['domain_blocked'] = FilterRule::isDomainBlocked($domain);
                $result['normalized'] = \App\Helpers\EmailHelper::normalize($validated['test_value']);
                break;
        }

        return response()->json($result);
    }
}
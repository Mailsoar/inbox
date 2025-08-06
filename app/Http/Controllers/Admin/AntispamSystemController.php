<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AntispamSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AntispamSystemController extends Controller
{
    /**
     * Display listing of antispam systems
     */
    public function index()
    {
        return view('admin.antispam-systems.index');
    }
    
    /**
     * Show form to create new custom antispam system
     */
    public function create()
    {
        return view('admin.antispam-systems.create');
    }
    
    /**
     * Store new custom antispam system
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:antispam_systems,name|regex:/^[a-z0-9_]+$/',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'header_patterns' => 'required|array|min:1',
            'header_patterns.*' => 'required|string|min:3',
        ], [
            'name.regex' => 'Le nom doit contenir uniquement des lettres minuscules, chiffres et underscores.',
            'header_patterns.*.min' => 'Chaque pattern doit contenir au moins 3 caractères.',
        ]);
        
        try {
            $system = AntispamSystem::create([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'header_patterns' => array_filter($request->header_patterns),
                'is_custom' => false,
                'is_active' => true,
            ]);
            
            Log::info('Custom antispam system created', [
                'system_id' => $system->id,
                'name' => $system->name,
            ]);
            
            return redirect()
                ->route('admin.antispam-systems.index')
                ->with('success', 'Système anti-spam créé avec succès.');
                
        } catch (\Exception $e) {
            Log::error('Error creating antispam system', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Erreur lors de la création : ' . $e->getMessage());
        }
    }
    
    /**
     * Show form to edit antispam system
     */
    public function edit(AntispamSystem $antispamSystem)
    {
        return view('admin.antispam-systems.edit', compact('antispamSystem'));
    }
    
    /**
     * Update antispam system
     */
    public function update(Request $request, AntispamSystem $antispamSystem)
    {
        $request->validate([
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'header_patterns' => 'required|array|min:1',
            'header_patterns.*' => 'required|string|min:3',
            'is_active' => 'boolean',
        ]);
        
        try {
            $antispamSystem->update([
                'display_name' => $request->display_name,
                'description' => $request->description,
                'header_patterns' => array_filter($request->header_patterns),
                'mx_patterns' => array_filter($request->mx_patterns ?? []),
                'is_active' => $request->boolean('is_active'),
            ]);
            
            return redirect()
                ->route('admin.antispam-systems.index')
                ->with('success', 'Système anti-spam mis à jour avec succès.');
                
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }
    }
    
    /**
     * Delete antispam system
     */
    public function destroy(AntispamSystem $antispamSystem)
    {
        // Check if system is in use
        if ($antispamSystem->emailAccounts()->count() > 0) {
            return redirect()
                ->route('admin.antispam-systems.index')
                ->with('error', 'Ce système est utilisé par des comptes email et ne peut pas être supprimé.');
        }
        
        try {
            $antispamSystem->delete();
            
            return redirect()
                ->route('admin.antispam-systems.index')
                ->with('success', 'Système anti-spam supprimé avec succès.');
                
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.antispam-systems.index')
                ->with('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }
    }
    
    /**
     * Test header patterns against sample headers
     */
    public function testPatterns(Request $request)
    {
        $request->validate([
            'patterns' => 'required|array',
            'headers' => 'required|string',
        ]);
        
        $emailHeaders = $request->input('headers');
        
        $matches = [];
        foreach ($request->patterns as $pattern) {
            if (!empty($pattern)) {
                // Check if pattern contains regex special characters
                if (preg_match('/[.*+?^${}()\[\]\\|]/', $pattern)) {
                    // Treat as regex pattern
                    try {
                        if (preg_match('/' . $pattern . '/i', $emailHeaders)) {
                            $matches[] = $pattern;
                        }
                    } catch (\Exception $e) {
                        // If regex is invalid, fall back to literal search
                        if (stripos($emailHeaders, $pattern) !== false) {
                            $matches[] = $pattern;
                        }
                    }
                } else {
                    // Treat as literal string
                    if (stripos($emailHeaders, $pattern) !== false) {
                        $matches[] = $pattern;
                    }
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'matches' => $matches,
            'matched' => count($matches) > 0,
        ]);
    }
    
    /**
     * Reset all antispam systems to default
     */
    public function reset()
    {
        try {
            DB::beginTransaction();
            
            // Default systems configuration
            $defaultSystems = [
                [
                    'name' => 'gmail',
                    'display_name' => 'Gmail (Google)',
                    'description' => 'Système anti-spam de Gmail',
                    'header_patterns' => ['X-Gm-', 'ARC-Authentication-Results:.*mx.google.com', 'X-Google-'],
                ],
                [
                    'name' => 'microsoft',
                    'display_name' => 'Microsoft/Outlook',
                    'description' => 'Système anti-spam de Microsoft Exchange/Outlook',
                    'header_patterns' => ['X-MS-Exchange-', 'X-Microsoft-Antispam', 'X-Forefront-', 'X-Exchange-Antispam'],
                ],
                [
                    'name' => 'yahoo',
                    'display_name' => 'Yahoo Mail',
                    'description' => 'Système anti-spam de Yahoo',
                    'header_patterns' => ['X-YMail-', 'X-Yahoo-', 'X-YahooFilteredBulk'],
                ],
                [
                    'name' => 'spamassassin',
                    'display_name' => 'SpamAssassin',
                    'description' => 'SpamAssassin open source',
                    'header_patterns' => ['X-Spam-', 'SpamAssassin'],
                ],
                [
                    'name' => 'rspamd',
                    'display_name' => 'Rspamd',
                    'description' => 'Rspamd - Fast spam filtering system',
                    'header_patterns' => ['X-Rspamd-', 'Rspamd'],
                ],
                [
                    'name' => 'amavis',
                    'display_name' => 'Amavis',
                    'description' => 'Amavisd-new mail content checker',
                    'header_patterns' => ['X-Amavis-', 'Amavis'],
                ],
                [
                    'name' => 'barracuda',
                    'display_name' => 'Barracuda',
                    'description' => 'Barracuda Spam Firewall',
                    'header_patterns' => ['X-Barracuda-'],
                ],
                [
                    'name' => 'mimecast',
                    'display_name' => 'Mimecast',
                    'description' => 'Mimecast Email Security',
                    'header_patterns' => ['X-Mimecast-'],
                ],
                [
                    'name' => 'proofpoint',
                    'display_name' => 'Proofpoint',
                    'description' => 'Proofpoint Email Protection',
                    'header_patterns' => ['X-Proofpoint-'],
                ],
                [
                    'name' => 'symantec',
                    'display_name' => 'Symantec',
                    'description' => 'Symantec Email Security',
                    'header_patterns' => ['X-Symantec-'],
                ],
                [
                    'name' => 'postfix',
                    'display_name' => 'Postfix',
                    'description' => 'Postfix MTA',
                    'header_patterns' => ['X-Postfix-', 'X-Dovecot-'],
                ],
                [
                    'name' => 'cpanel',
                    'display_name' => 'cPanel/WHM',
                    'description' => 'cPanel/WHM Email',
                    'header_patterns' => ['X-cPanel-', 'X-WHM-'],
                ],
                [
                    'name' => 'none',
                    'display_name' => 'Aucun / Non configuré',
                    'description' => 'Aucun système anti-spam configuré',
                    'header_patterns' => [],
                ],
            ];
            
            // Disable all non-default systems
            AntispamSystem::whereNotIn('name', array_column($defaultSystems, 'name'))
                ->update(['is_active' => false]);
            
            // Reset default systems
            foreach ($defaultSystems as $system) {
                AntispamSystem::updateOrCreate(
                    ['name' => $system['name']],
                    [
                        'display_name' => $system['display_name'],
                        'description' => $system['description'],
                        'header_patterns' => $system['header_patterns'],
                        'is_custom' => false,
                        'is_active' => true,
                    ]
                );
            }
            
            DB::commit();
            
            return redirect()
                ->route('admin.antispam-systems.index')
                ->with('success', 'Les systèmes anti-spam ont été réinitialisés avec succès.');
                
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error resetting antispam systems', [
                'error' => $e->getMessage(),
            ]);
            
            return redirect()
                ->route('admin.antispam-systems.index')
                ->with('error', 'Erreur lors de la réinitialisation : ' . $e->getMessage());
        }
    }
}
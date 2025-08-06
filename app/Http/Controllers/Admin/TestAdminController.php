<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\TestResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestAdminController extends Controller
{
    public function index(Request $request)
    {
        // Récupérer la période depuis la requête, la session ou la préférence utilisateur
        $user = auth('admin')->user();
        
        // Priorité : 1. Requête, 2. Session, 3. Préférence utilisateur, 4. Défaut
        if ($request->has('period')) {
            $period = $request->get('period');
            // Sauvegarder dans la session pour synchronisation entre pages
            session(['admin_period' => $period]);
        } else {
            $period = session('admin_period', $user->dashboard_period ?? '7');
        }
        
        // Sauvegarder la préférence utilisateur si elle a changé
        if ($period !== $user->dashboard_period) {
            $user->dashboard_period = $period;
            $user->save();
        }
        
        $period = (int) $period;
        $periodStart = now()->subDays($period)->startOfDay();
        
        // Vérifier si on doit afficher l'historique
        $showHistory = $request->get('history', false);
        
        $query = Test::with(['receivedEmails', 'emailAccounts']);

        // Appliquer le filtre de période global
        $query->where('created_at', '>=', $periodStart);

        // Filtres additionnels
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('unique_id', 'like', '%' . $request->search . '%')
                  ->orWhere('visitor_email', 'like', '%' . $request->search . '%');
            });
        }

        $tests = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Enrichir les données pour l'affichage
        $tests->getCollection()->transform(function ($test) {
            $receivedEmails = $test->receivedEmails;
            $totalAccounts = $test->emailAccounts->count();
            
            // Récupérer les stats d'authentification depuis ReceivedEmail
            $authStats = TestResult::where('test_id', $test->id)
                ->select(
                    DB::raw('SUM(spf_result = "pass") as spf_pass'),
                    DB::raw('SUM(dkim_result = "pass") as dkim_pass'),
                    DB::raw('SUM(dmarc_result = "pass") as dmarc_pass'),
                    DB::raw('COUNT(*) as total')
                )
                ->first();
            
            $authScore = null; // null = pas de données
            if ($authStats && $authStats->total > 0) {
                $authScore = round((
                    ($authStats->spf_pass ?? 0) + 
                    ($authStats->dkim_pass ?? 0) + 
                    ($authStats->dmarc_pass ?? 0)
                ) / ($authStats->total * 3) * 100);
            }
            
            return (object) [
                'id' => $test->id,
                'unique_id' => $test->unique_id,
                'visitor_email' => $test->visitor_email,
                'audience_type' => $test->audience_type,
                'status' => $test->status,
                'created_at' => $test->created_at,
                'total_accounts' => $totalAccounts,
                'received_count' => $receivedEmails->count(),
                'inbox_count' => $receivedEmails->whereIn('placement', \App\Models\Test::getInboxPlacements())->count(),
                'spam_count' => $receivedEmails->where('placement', 'spam')->count(),
                'completion_rate' => $totalAccounts > 0 ? round(($receivedEmails->count() / $totalAccounts) * 100, 1) : 0,
                'auth_score' => $authScore,
                'has_auth_issues' => $authScore !== null && $authScore < 70,
                'spam_rate' => $receivedEmails->count() > 0 
                    ? round(($receivedEmails->where('placement', 'spam')->count() / $receivedEmails->count()) * 100)
                    : 0,
            ];
        });

        // Statistiques pour les filtres basées sur la période
        $statusCounts = Test::where('created_at', '>=', $periodStart)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Garantir que tous les statuts sont présents
        $statusCounts = array_merge([
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'timeout' => 0,
        ], $statusCounts);

        // Statistiques des tests - Combinaison intelligente historique + live
        if ($period == 1) {
            $historyStats = null;
            // Dernières 24h : afficher par heure (données live uniquement)
            $testsPerHour = Test::where('created_at', '>=', now()->subHours(24))
                ->select(
                    DB::raw('HOUR(created_at) as hour'),
                    DB::raw('count(*) as total'),
                    DB::raw('SUM(status = "pending") as pending'),
                    DB::raw('SUM(status = "in_progress") as in_progress'),
                    DB::raw('SUM(status = "completed") as completed'),
                    DB::raw('SUM(status = "cancelled") as cancelled'),
                    DB::raw('SUM(status = "timeout") as timeout')
                )
                ->groupBy(DB::raw('HOUR(created_at)'))
                ->orderBy('hour')
                ->get()
                ->map(function ($item) {
                    $total = $item->total;
                    return [
                        'hour' => $item->hour,
                        'label' => $item->hour . 'h',
                        'total' => $total,
                        'pending_percent' => $total > 0 ? round(($item->pending / $total) * 100, 1) : 0,
                        'progress_percent' => $total > 0 ? round(($item->in_progress / $total) * 100, 1) : 0,
                        'completed_percent' => $total > 0 ? round(($item->completed / $total) * 100, 1) : 0,
                        'cancelled_percent' => $total > 0 ? round(($item->cancelled / $total) * 100, 1) : 0,
                        'timeout_percent' => $total > 0 ? round(($item->timeout / $total) * 100, 1) : 0,
                    ];
                });
        } else {
            // Plus de 24h : Combiner historique + données live
            $retentionDays = config('mailsoar.test_retention_days', 7);
            $retentionCutoff = now()->subDays($retentionDays)->startOfDay();
            
            // 1. Récupérer les données historiques pour les dates > retention
            $historicalData = [];
            if ($periodStart < $retentionCutoff) {
                $historicalData = DB::table('test_metrics_history')
                    ->where('metric_date', '>=', $periodStart->format('Y-m-d'))
                    ->where('metric_date', '<', $retentionCutoff->format('Y-m-d'))
                    ->select(
                        'metric_date as date',
                        'total_tests as total',
                        'pending_tests as pending',
                        'in_progress_tests as in_progress',
                        'completed_tests as completed',
                        'timeout_tests as timeout'
                    )
                    ->get()
                    ->keyBy('date');
            }
            
            // 2. Récupérer les données live pour les dates récentes
            $liveData = Test::where('created_at', '>=', max($periodStart, $retentionCutoff))
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('count(*) as total'),
                    DB::raw('SUM(status = "pending") as pending'),
                    DB::raw('SUM(status = "in_progress") as in_progress'),
                    DB::raw('SUM(status = "completed") as completed'),
                    DB::raw('SUM(status = "cancelled") as cancelled'),
                    DB::raw('SUM(status = "timeout") as timeout')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->get()
                ->keyBy('date');
            
            // 3. Fusionner les données
            $allDates = collect($historicalData)->merge($liveData)->sortKeys();
            
            $testsPerHour = $allDates->map(function ($item) {
                $total = $item->total ?? 0;
                $cancelled = $item->cancelled ?? 0; // Peut être null dans l'historique
                
                return [
                    'hour' => 0,
                    'label' => \Carbon\Carbon::parse($item->date)->format('M d'),
                    'total' => $total,
                    'pending_percent' => $total > 0 ? round(($item->pending / $total) * 100, 1) : 0,
                    'progress_percent' => $total > 0 ? round((($item->{'in-progress'} ?? $item->in_progress) / $total) * 100, 1) : 0,
                    'completed_percent' => $total > 0 ? round(($item->completed / $total) * 100, 1) : 0,
                    'cancelled_percent' => $total > 0 ? round(($cancelled / $total) * 100, 1) : 0,
                    'timeout_percent' => $total > 0 ? round(($item->timeout / $total) * 100, 1) : 0,
                ];
            })->values();
            
            // Stats pour l'affichage
            if ($showHistory) {
                // Mode historique complet
                $historyStats = DB::table('test_metrics_history')
                    ->where('metric_date', '>=', $periodStart->format('Y-m-d'))
                    ->select(
                        DB::raw('SUM(total_tests) as total_tests'),
                        DB::raw('SUM(completed_tests) as completed_tests'),
                        DB::raw('SUM(pending_tests) as pending_tests'),
                        DB::raw('SUM(in_progress_tests) as in_progress_tests'),
                        DB::raw('SUM(timeout_tests) as timeout_tests'),
                        DB::raw('AVG(inbox_rate) as avg_inbox_rate'),
                        DB::raw('AVG(spam_rate) as avg_spam_rate')
                    )
                    ->first();
            } else {
                $historyStats = null;
            }
        }

        // Top 10 des visiteurs avec problèmes (leads potentiels)
        $topVisitors = Test::where('created_at', '>=', $periodStart)
            ->select(
                'visitor_email',
                DB::raw('count(*) as test_count'),
                DB::raw('MAX(created_at) as last_test'),
                DB::raw('GROUP_CONCAT(DISTINCT audience_type) as audience_types'),
                DB::raw('AVG(received_emails) as avg_received'),
                DB::raw('SUM(status = "completed") as completed_tests')
            )
            ->groupBy('visitor_email')
            ->orderByDesc('test_count')
            ->limit(10)
            ->get()
            ->map(function ($visitor) use ($periodStart) {
                // Calculer le taux de spam moyen pour ce visiteur
                $visitorTests = Test::where('visitor_email', $visitor->visitor_email)
                    ->where('created_at', '>=', $periodStart)
                    ->where('status', 'completed')
                    ->pluck('id');
                
                $spamStats = TestResult::whereIn('test_id', $visitorTests)
                    ->select(
                        DB::raw('COUNT(*) as total'),
                        DB::raw('SUM(placement = "spam") as spam_count')
                    )
                    ->first();
                
                $spamRate = $spamStats && $spamStats->total > 0 
                    ? round(($spamStats->spam_count / $spamStats->total) * 100)
                    : 0;
                
                // Vérifier les problèmes d'authentification depuis ReceivedEmail
                $authStats = TestResult::whereIn('test_id', $visitorTests)
                    ->select(
                        DB::raw('AVG(CASE WHEN spf_result = "pass" THEN 100 ELSE 0 END) as spf_score'),
                        DB::raw('AVG(CASE WHEN dkim_result = "pass" THEN 100 ELSE 0 END) as dkim_score'),
                        DB::raw('AVG(CASE WHEN dmarc_result = "pass" THEN 100 ELSE 0 END) as dmarc_score')
                    )
                    ->first();
                
                $authScore = $authStats && $authStats->spf_score !== null
                    ? round(($authStats->spf_score + $authStats->dkim_score + $authStats->dmarc_score) / 3)
                    : null;
                
                return [
                    'email' => $visitor->visitor_email,
                    'test_count' => $visitor->test_count,
                    'last_test' => $visitor->last_test,
                    'audience_types' => explode(',', $visitor->audience_types),
                    'spam_rate' => $spamRate,
                    'auth_score' => $authScore,
                    'has_issues' => $spamRate > 30 || ($authScore !== null && $authScore < 70),
                    'issue_type' => $spamRate > 30 ? 'spam' : (($authScore !== null && $authScore < 70) ? 'auth' : null),
                ];
            });

        return view('admin.tests.index', compact(
            'tests', 
            'statusCounts', 
            'testsPerHour', 
            'topVisitors',
            'period',
            'showHistory',
            'historyStats'
        ));
    }

    public function show(Test $test)
    {
        $test->load(['receivedEmails.emailAccount', 'emailAccounts']);

        // Statistiques détaillées
        $stats = [
            'total_accounts' => $test->emailAccounts->count(),
            'received_count' => $test->receivedEmails->count(),
            'pending_count' => $test->emailAccounts->count() - $test->receivedEmails->count(),
        ];

        // Répartition par placement
        $placementStats = $test->receivedEmails
            ->groupBy('placement')
            ->map->count()
            ->toArray();

        // Répartition par provider
        $providerStats = $test->receivedEmails
            ->groupBy('emailAccount.provider')
            ->map(function ($emails) {
                return [
                    'total' => $emails->count(),
                    'inbox' => $emails->whereIn('placement', \App\Models\Test::getInboxPlacements())->count(),
                    'spam' => $emails->where('placement', 'spam')->count(),
                ];
            })
            ->toArray();

        // Analyse d'authentification depuis ReceivedEmail
        $authStats = [
            'spf' => TestResult::where('test_id', $test->id)
                ->groupBy('spf_result')
                ->select('spf_result', DB::raw('count(*) as count'))
                ->pluck('count', 'spf_result')
                ->toArray(),
            'dkim' => TestResult::where('test_id', $test->id)
                ->groupBy('dkim_result')
                ->select('dkim_result', DB::raw('count(*) as count'))
                ->pluck('count', 'dkim_result')
                ->toArray(),
            'dmarc' => TestResult::where('test_id', $test->id)
                ->groupBy('dmarc_result')
                ->select('dmarc_result', DB::raw('count(*) as count'))
                ->pluck('count', 'dmarc_result')
                ->toArray(),
        ];

        return view('admin.tests.show', compact('test', 'stats', 'placementStats', 'providerStats', 'authStats'));
    }

    public function destroy(Test $test)
    {
        $test->delete();
        
        return redirect()->route('admin.tests.index')
            ->with('success', 'Test supprimé avec succès');
    }

    /**
     * Force recheck of a test
     */
    public function forceRecheck(Test $test)
    {
        // Permettre la re-vérification de tous les tests (même completed)
        // pour permettre de trouver des emails manqués
        if ($test->status === 'cancelled') {
            return redirect()->route('admin.tests.index')
                ->with('error', 'Ce test a été annulé et ne peut pas être re-vérifié.');
        }

        try {
            // Déterminer si on doit effacer les résultats existants
            $clearExisting = request()->get('clear', false);
            
            if ($clearExisting) {
                // Re-vérification complète : effacer tous les résultats
                
                // 1. Supprimer tous les test results
                TestResult::where('test_id', $test->id)->delete();
                
                // 2. Réinitialiser le compteur
                $test->received_emails = 0;
                
                // 3. Réinitialiser la table pivot
                DB::table('test_email_accounts')
                    ->where('test_id', $test->id)
                    ->update([
                        'email_received' => false,
                        'received_at' => null,
                        'last_checked_at' => null
                    ]);
                    
                $message = "La re-vérification complète du test #{$test->unique_id} a été lancée (résultats existants effacés).";
            } else {
                // Re-vérification simple : garder les résultats existants
                
                // Juste réinitialiser last_checked_at pour forcer une nouvelle vérification
                DB::table('test_email_accounts')
                    ->where('test_id', $test->id)
                    ->where('email_received', false) // Seulement pour les non-reçus
                    ->update([
                        'last_checked_at' => null
                    ]);
                    
                $message = "La re-vérification du test #{$test->unique_id} a été lancée (recherche des emails manquants).";
            }
            
            // Mettre à jour le statut du test
            $test->status = 'in_progress';
            $test->timeout_at = now()->addMinutes(30);
            $test->save();
            
            // Les jobs ProcessEmailAddressJob vont automatiquement traiter ce test
            // car il est maintenant in_progress avec un timeout_at dans le futur

            return redirect()->route('admin.tests.index')
                ->with('success', $message)
                ->with('info', 'Le test sera traité automatiquement dans les prochaines minutes.');
        } catch (\Exception $e) {
            return redirect()->route('admin.tests.index')
                ->with('error', 'Erreur lors de la re-vérification: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a pending test
     */
    public function cancel(Test $test)
    {
        // Vérifier que le test peut être annulé
        if ($test->status !== 'pending') {
            return redirect()->route('admin.tests.index')
                ->with('error', 'Seuls les tests en attente peuvent être annulés.');
        }

        try {
            $test->status = 'cancelled';
            $test->save();

            return redirect()->route('admin.tests.index')
                ->with('success', "Le test #{$test->unique_id} a été annulé.");
        } catch (\Exception $e) {
            return redirect()->route('admin.tests.index')
                ->with('error', 'Erreur lors de l\'annulation: ' . $e->getMessage());
        }
    }
}
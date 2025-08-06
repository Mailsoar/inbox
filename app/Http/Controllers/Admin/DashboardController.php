<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\EmailAccount;
use App\Models\TestResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
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
        
        // Calculer les dates selon la période
        $periodDays = (int) $period;
        $last24Hours = now()->subHours(24);
        $last7Days = now()->subDays(7);
        $lastPeriod = now()->subDays($periodDays);
        
        // === ALERTES SYSTÈME ===
        $systemAlerts = $this->getSystemAlerts();
        
        // === MONITORING DES COMPTES EMAIL ===
        $emailAccountsHealth = $this->getEmailAccountsHealth();
        
        // === MONITORING DES FILTRES ANTI-SPAM ===
        $spamFiltersHealth = $this->getSpamFiltersHealth();
        
        // === STATISTIQUES SYSTÈME ===
        $systemStats = $this->getSystemStats($last24Hours, $lastPeriod);
        
        // === TESTS RÉCENTS AVEC PROBLÈMES ===
        $problematicTests = $this->getProblematicTests($last24Hours);
        
        // === GRAPHIQUES ===
        // Évolution des tests sur la période sélectionnée
        $testsTrend = $this->getTestsTrend($lastPeriod);
        
        // Distribution des erreurs par type
        $errorTypes = $this->getErrorTypes($lastPeriod);

        return view('admin.dashboard', compact(
            'systemAlerts',
            'emailAccountsHealth',
            'spamFiltersHealth',
            'systemStats',
            'problematicTests',
            'testsTrend',
            'errorTypes',
            'period'
        ));
    }

    private function getSystemAlerts(): array
    {
        $alerts = [
            'critical' => [],
            'warning' => [],
            'info' => []
        ];

        // Vérifier les tokens OAuth expirés ou proches de l'expiration
        // UNIQUEMENT pour les comptes qui utilisent réellement OAuth
        $tokenExpirationCheck = EmailAccount::where('is_active', true)
            ->where(function($query) {
                // Vérifier que le compte utilise OAuth (a un oauth_token ou auth_type = 'oauth')
                $query->whereNotNull('oauth_token')
                    ->orWhere('auth_type', 'oauth');
            })
            ->where(function($query) {
                $query->whereNull('last_token_refresh')
                    ->orWhere('last_token_refresh', '<', now()->subDays(50)); // OAuth tokens usually expire after 60 days
            })
            ->get();

        foreach ($tokenExpirationCheck as $account) {
            // Vérifier que le compte utilise bien OAuth avant d'alerter
            if (!$account->oauth_token && $account->auth_type !== 'oauth') {
                continue;
            }
            
            $daysSinceRefresh = $account->last_token_refresh 
                ? $account->last_token_refresh->diffInDays(now()) 
                : 999;
                
            if ($daysSinceRefresh > 55) {
                $alerts['critical'][] = [
                    'type' => 'token_expired',
                    'message' => "Token OAuth expiré pour {$account->email}",
                    'account_id' => $account->id,
                    'provider' => $account->provider
                ];
            } elseif ($daysSinceRefresh > 45) {
                $alerts['warning'][] = [
                    'type' => 'token_expiring',
                    'message' => "Token OAuth expire bientôt pour {$account->email} ({$daysSinceRefresh} jours)",
                    'account_id' => $account->id,
                    'provider' => $account->provider
                ];
            }
        }

        // Vérifier les comptes avec échecs de connexion récents
        $failedConnections = EmailAccount::where('is_active', true)
            ->where('connection_status', 'failed')
            ->where('last_connection_check', '>=', now()->subHours(24))
            ->get();

        foreach ($failedConnections as $account) {
            $alerts['critical'][] = [
                'type' => 'connection_failed',
                'message' => "Impossible de se connecter à {$account->email}",
                'account_id' => $account->id,
                'provider' => $account->provider
            ];
        }

        // Vérifier si on a assez de comptes actifs par provider
        $activeByProvider = EmailAccount::where('is_active', true)
            ->select('provider', DB::raw('count(*) as count'))
            ->groupBy('provider')
            ->pluck('count', 'provider')
            ->toArray();

        foreach (['gmail', 'outlook', 'yahoo'] as $provider) {
            $count = $activeByProvider[$provider] ?? 0;
            if ($count === 0) {
                $alerts['critical'][] = [
                    'type' => 'no_active_provider',
                    'message' => "Aucun compte {$provider} actif !",
                    'provider' => $provider
                ];
            } elseif ($count === 1) {
                $alerts['warning'][] = [
                    'type' => 'low_provider_count',
                    'message' => "Seulement 1 compte {$provider} actif",
                    'provider' => $provider
                ];
            }
        }

        // Vérifier les taux d'échec des tests récents
        $recentTestsStats = Test::where('created_at', '>=', now()->subHours(6))
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalRecent = array_sum($recentTestsStats);
        $failedRecent = ($recentTestsStats['failed'] ?? 0) + ($recentTestsStats['cancelled'] ?? 0);
        
        if ($totalRecent > 10 && ($failedRecent / $totalRecent) > 0.3) {
            $failureRate = round(($failedRecent / $totalRecent) * 100);
            $alerts['warning'][] = [
                'type' => 'high_failure_rate',
                'message' => "Taux d'échec élevé : {$failureRate}% des tests récents",
                'stats' => $recentTestsStats
            ];
        }

        return $alerts;
    }

    private function getEmailAccountsHealth(): array
    {
        $accounts = EmailAccount::with(['receivedEmails' => function($query) {
            $query->where('created_at', '>=', now()->subDays(7))
                ->select('email_account_id', 'placement', 'created_at');
        }])->get();

        $health = [];
        
        foreach ($accounts as $account) {
            $recentEmails = $account->receivedEmails;
            $lastEmail = $account->receivedEmails()->latest()->first();
            
            // Calculer le statut de santé
            $status = 'healthy';
            $issues = [];
            
            // Calculer l'âge du compte
            $accountAge = $account->created_at->diffInDays(now());
            
            // Vérifier la dernière activité
            $daysSinceLastEmail = $lastEmail 
                ? $lastEmail->created_at->diffInDays(now()) 
                : null;
                
            if (!$account->is_active) {
                $status = 'inactive';
                $issues[] = 'Compte désactivé';
            } elseif ($account->connection_status === 'failed' || $account->connection_status === 'error') {
                $status = 'critical';
                $issues[] = 'Échec de connexion';
            } elseif ($daysSinceLastEmail === null) {
                // Compte sans aucun email reçu
                if ($accountAge > 7) {
                    // Seulement alerter si le compte a plus de 7 jours
                    $status = 'warning';
                    $issues[] = "Aucun email reçu (compte créé il y a {$accountAge} jours)";
                } else {
                    // Nouveau compte, pas d'alerte
                    $status = 'healthy';
                    if ($accountAge <= 1) {
                        $issues[] = 'Nouveau compte (créé aujourd\'hui)';
                    } else {
                        $issues[] = "Nouveau compte (créé il y a {$accountAge} jours)";
                    }
                }
            } elseif ($daysSinceLastEmail > 7) {
                $status = 'warning';
                $issues[] = "Aucun email reçu depuis {$daysSinceLastEmail} jours";
            } elseif ($account->oauth_token && $account->last_token_refresh && $account->last_token_refresh->diffInDays() > 45) {
                $status = 'warning';
                $issues[] = 'Token OAuth bientôt expiré';
            }
            
            // Calculer les stats de placement
            $placementStats = $recentEmails->groupBy('placement')->map->count();
            $spamRate = $recentEmails->count() > 0 
                ? ($placementStats->get('spam', 0) / $recentEmails->count()) * 100 
                : 0;
                
            if ($spamRate > 50 && $recentEmails->count() > 5) {
                $status = $status === 'healthy' ? 'warning' : $status;
                $issues[] = "Taux de spam élevé : " . round($spamRate) . "%";
            }
            
            $health[] = [
                'account' => $account,
                'status' => $status,
                'issues' => $issues,
                'stats' => [
                    'total_recent' => $recentEmails->count(),
                    'spam_rate' => round($spamRate, 1),
                    'days_since_last_email' => $daysSinceLastEmail ?? $accountAge,
                    'last_activity' => $lastEmail ? $lastEmail->created_at : null
                ]
            ];
        }
        
        // Trier par statut (critical > warning > inactive > healthy)
        $statusOrder = ['critical' => 0, 'warning' => 1, 'inactive' => 2, 'healthy' => 3];
        usort($health, function($a, $b) use ($statusOrder) {
            return $statusOrder[$a['status']] <=> $statusOrder[$b['status']];
        });
        
        return $health;
    }

    private function getSpamFiltersHealth(): array
    {
        // Récupérer les systèmes anti-spam depuis la base
        $antispamSystems = \App\Models\AntispamSystem::withCount('emailAccounts')
            ->orderBy('display_name')
            ->get();
        
        // Récupérer les comptes actifs
        $activeAccountIds = EmailAccount::where('is_active', true)->pluck('id');
        
        // Récupérer les emails récents pour analyser l'utilisation
        $recentEmails = TestResult::where('created_at', '>=', now()->subDays(7))
            ->whereIn('email_account_id', $activeAccountIds)
            ->get();
        
        // Construire le rapport de santé basé sur les systèmes configurés
        $health = [];
        
        foreach ($antispamSystems as $system) {
            $status = 'healthy';
            $message = '';
            
            // Vérifier le nombre de comptes liés
            $accountCount = $system->email_accounts_count;
            
            // Compter les patterns configurés
            $patternCount = 0;
            if ($system->header_patterns) $patternCount += count($system->header_patterns);
            if ($system->subject_patterns) $patternCount += count($system->subject_patterns);
            if ($system->body_patterns) $patternCount += count($system->body_patterns);
            
            // Déterminer le statut
            if (!$system->is_active) {
                $status = 'critical';
                $message = "Système désactivé";
            } elseif ($accountCount === 0) {
                $status = 'warning';
                $message = "Aucun compte associé";
            } elseif ($patternCount === 0) {
                $status = 'warning';
                $message = "Aucun pattern configuré";
            } elseif ($accountCount === 1) {
                $status = 'info';
                $message = "1 compte teste ce filtre";
            } elseif ($accountCount < 3) {
                $status = 'info';
                $message = "{$accountCount} comptes testent ce filtre";
            } else {
                $message = "{$accountCount} comptes actifs, {$patternCount} patterns";
            }
            
            // Compter les détections récentes
            $detections = 0;
            foreach ($recentEmails as $email) {
                if ($email->spam_scores && is_array($email->spam_scores)) {
                    if (isset($email->spam_scores[$system->name])) {
                        $detections++;
                    }
                }
            }
            
            $health[] = [
                'filter' => $system->name,
                'name' => $system->display_name,
                'status' => $status,
                'message' => $message,
                'coverage' => $detections,
                'account_count' => $accountCount
            ];
        }
        
        // Trier par statut
        $statusOrder = ['critical' => 0, 'warning' => 1, 'info' => 2, 'healthy' => 3];
        usort($health, function($a, $b) use ($statusOrder) {
            return $statusOrder[$a['status']] <=> $statusOrder[$b['status']];
        });
        
        return $health;
    }

    private function getSystemStats($last24Hours, $last7Days): array
    {
        return [
            'total_tests_24h' => Test::where('created_at', '>=', $last24Hours)->count(),
            'active_tests' => Test::where('status', 'in_progress')->count(),
            'failed_tests_24h' => Test::where('created_at', '>=', $last24Hours)
                ->whereIn('status', ['failed', 'cancelled'])
                ->count(),
            'total_emails_7d' => TestResult::where('created_at', '>=', $last7Days)->count(),
            'active_accounts' => EmailAccount::where('is_active', true)->count(),
            'total_accounts' => EmailAccount::count(),
            'providers_active' => EmailAccount::where('is_active', true)
                ->distinct('provider')
                ->count('provider'),
        ];
    }

    private function getProblematicTests($since): array
    {
        return Test::where('created_at', '>=', $since)
            ->where(function($query) {
                $query->where('status', 'failed')
                    ->orWhere('status', 'cancelled')
                    ->orWhereRaw('(status = "completed" AND received_emails = 0)');
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($test) {
                return [
                    'test' => $test,
                    'issue' => $this->determineTestIssue($test)
                ];
            })
            ->toArray();
    }

    private function determineTestIssue($test): string
    {
        if ($test->status === 'failed') {
            return 'Test échoué';
        } elseif ($test->status === 'cancelled') {
            return 'Test annulé';
        } elseif ($test->received_emails === 0) {
            return 'Aucun email reçu';
        }
        return 'Problème inconnu';
    }

    private function getTestsTrend($since): array
    {
        $trend = [];
        $hours = $since->diffInHours(now());
        
        // Si la période est de 24h ou moins, afficher par heure (données live uniquement)
        if ($hours <= 24) {
            for ($i = $hours; $i >= 0; $i--) {
                $hour = now()->subHours($i);
                $startOfHour = $hour->copy()->startOfHour();
                $endOfHour = $hour->copy()->endOfHour();
                
                // Récupérer les statistiques par statut pour l'heure
                $stats = Test::whereBetween('created_at', [$startOfHour, $endOfHour])
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();
                    
                $total = array_sum($stats);
                $cancelled = $stats['cancelled'] ?? 0;
                $failed = $stats['failed'] ?? 0;
                $completed = $stats['completed'] ?? 0;
                $timeout = $stats['timeout'] ?? 0;
                
                // Calculer les pourcentages
                $cancelledPercent = $total > 0 ? ($cancelled / $total) * 100 : 0;
                $failedPercent = $total > 0 ? ($failed / $total) * 100 : 0;
                $completedPercent = $total > 0 ? ($completed / $total) * 100 : 0;
                $timeoutPercent = $total > 0 ? ($timeout / $total) * 100 : 0;
                    
                $trend[] = [
                    'date' => $hour->format('H:00'),
                    'datetime' => $hour->format('Y-m-d H:00'),
                    'total' => $total,
                    'cancelled' => $cancelled,
                    'failed' => $failed,
                    'completed' => $completed,
                    'timeout' => $timeout,
                    'cancelled_percent' => round($cancelledPercent, 1),
                    'failed_percent' => round($failedPercent, 1),
                    'completed_percent' => round($completedPercent, 1),
                    'timeout_percent' => round($timeoutPercent, 1)
                ];
            }
        } else {
            // Pour les périodes plus longues, combiner historique + live
            $days = $since->diffInDays(now());
            $retentionDays = config('mailsoar.test_retention_days', 7);
            $retentionCutoff = now()->subDays($retentionDays)->startOfDay();
            
            // Récupérer toutes les dates nécessaires
            $allDates = [];
            for ($i = $days; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $allDates[$date->format('Y-m-d')] = $date;
            }
            
            // 1. Récupérer les données historiques pour les dates > retention
            if ($since < $retentionCutoff) {
                $historicalData = DB::table('test_metrics_history')
                    ->where('metric_date', '>=', $since->format('Y-m-d'))
                    ->where('metric_date', '<', $retentionCutoff->format('Y-m-d'))
                    ->select(
                        'metric_date',
                        'total_tests',
                        'completed_tests',
                        'pending_tests',
                        'in_progress_tests',
                        'timeout_tests'
                    )
                    ->get()
                    ->keyBy('metric_date');
            } else {
                $historicalData = collect();
            }
            
            // 2. Récupérer les données live pour chaque jour récent
            foreach ($allDates as $dateStr => $date) {
                $dateObj = Carbon::parse($dateStr);
                
                // Utiliser l'historique si disponible et si la date est au-delà de la retention
                if ($dateObj < $retentionCutoff && $historicalData->has($dateStr)) {
                    $histData = $historicalData->get($dateStr);
                    $total = $histData->total_tests;
                    $completed = $histData->completed_tests;
                    $timeout = $histData->timeout_tests;
                    $cancelled = 0; // Pas dans l'historique
                    $failed = 0; // Pas dans l'historique
                    $pending = $histData->pending_tests;
                    $inProgress = $histData->in_progress_tests;
                } else {
                    // Utiliser les données live
                    $startOfDay = $dateObj->copy()->startOfDay();
                    $endOfDay = $dateObj->copy()->endOfDay();
                    
                    $stats = Test::whereBetween('created_at', [$startOfDay, $endOfDay])
                        ->select('status', DB::raw('count(*) as count'))
                        ->groupBy('status')
                        ->pluck('count', 'status')
                        ->toArray();
                        
                    $total = array_sum($stats);
                    $cancelled = $stats['cancelled'] ?? 0;
                    $failed = $stats['failed'] ?? 0;
                    $completed = $stats['completed'] ?? 0;
                    $timeout = $stats['timeout'] ?? 0;
                    $pending = $stats['pending'] ?? 0;
                    $inProgress = $stats['in_progress'] ?? 0;
                }
                
                // Calculer les pourcentages
                $cancelledPercent = $total > 0 ? ($cancelled / $total) * 100 : 0;
                $failedPercent = $total > 0 ? ($failed / $total) * 100 : 0;
                $completedPercent = $total > 0 ? ($completed / $total) * 100 : 0;
                $timeoutPercent = $total > 0 ? ($timeout / $total) * 100 : 0;
                    
                $trend[] = [
                    'date' => $dateObj->format('M d'),
                    'datetime' => $dateStr,
                    'total' => $total,
                    'cancelled' => $cancelled,
                    'failed' => $failed,
                    'completed' => $completed,
                    'timeout' => $timeout,
                    'cancelled_percent' => round($cancelledPercent, 1),
                    'failed_percent' => round($failedPercent, 1),
                    'completed_percent' => round($completedPercent, 1),
                    'timeout_percent' => round($timeoutPercent, 1),
                    'is_historical' => $dateObj < $retentionCutoff
                ];
            }
        }
        
        return $trend;
    }

    private function getErrorTypes($since): array
    {
        // Ici on pourrait analyser les logs pour catégoriser les types d'erreurs
        // Pour l'instant, on utilise les statuts des tests
        return Test::where('created_at', '>=', $since)
            ->whereIn('status', ['failed', 'cancelled'])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Test;
use App\Models\TestResult;
use Carbon\Carbon;

class ArchiveTestMetricsCommand extends Command
{
    protected $signature = 'metrics:archive 
                            {--date= : Date to archive metrics for (YYYY-MM-DD). Default is yesterday}
                            {--force : Force archiving even if metrics exist for that date}';

    protected $description = 'Archive test metrics to history table';

    public function handle()
    {
        $dateString = $this->option('date');
        $force = $this->option('force');
        
        // Déterminer la date à archiver
        if ($dateString) {
            $date = Carbon::parse($dateString)->startOfDay();
        } else {
            // Par défaut, archiver les métriques d'hier
            $date = Carbon::yesterday()->startOfDay();
        }
        
        $endDate = $date->copy()->endOfDay();
        
        $this->info("===========================================");
        $this->info("📊 Archivage des métriques de tests");
        $this->info("===========================================");
        $this->info("Date: {$date->format('Y-m-d')}");
        $this->info("");
        
        // Vérifier si des métriques existent déjà pour cette date
        $existingMetrics = DB::table('test_metrics_history')
            ->where('metric_date', $date->format('Y-m-d'))
            ->first();
            
        if ($existingMetrics && !$force) {
            $this->warn("⚠️ Des métriques existent déjà pour cette date. Utilisez --force pour écraser.");
            return 1;
        }
        
        // Collecter les métriques
        $this->info("Collecte des métriques...");
        
        // Tests par statut
        $testStats = Test::whereBetween('created_at', [$date, $endDate])
            ->selectRaw('
                COUNT(*) as total,
                SUM(status = "completed") as completed,
                SUM(status = "pending") as pending,
                SUM(status = "in_progress") as in_progress,
                SUM(status = "timeout") as timeout
            ')
            ->first();
        
        // Emails et placements
        $emailStats = TestResult::whereHas('test', function($query) use ($date, $endDate) {
                $query->whereBetween('created_at', [$date, $endDate]);
            })
            ->selectRaw('
                COUNT(*) as total_emails,
                SUM(placement = "inbox") as inbox_count,
                SUM(placement = "spam") as spam_count
            ')
            ->first();
        
        // Visiteurs uniques
        $uniqueVisitors = Test::whereBetween('created_at', [$date, $endDate])
            ->distinct('visitor_email')
            ->count('visitor_email');
        
        // Stats par provider
        $providerStats = DB::table('tests')
            ->join('test_email_accounts', 'tests.id', '=', 'test_email_accounts.test_id')
            ->join('email_accounts', 'test_email_accounts.email_account_id', '=', 'email_accounts.id')
            ->whereBetween('tests.created_at', [$date, $endDate])
            ->selectRaw('
                email_accounts.provider,
                COUNT(DISTINCT tests.id) as test_count,
                SUM(test_email_accounts.email_received) as emails_received
            ')
            ->groupBy('email_accounts.provider')
            ->get();
        
        // Stats par audience
        $audienceStats = Test::whereBetween('created_at', [$date, $endDate])
            ->selectRaw('
                audience_type,
                COUNT(*) as count
            ')
            ->groupBy('audience_type')
            ->get();
        
        // Distribution horaire
        $hourlyDistribution = Test::whereBetween('created_at', [$date, $endDate])
            ->selectRaw('
                HOUR(created_at) as hour,
                COUNT(*) as count
            ')
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->get();
        
        // Calculer les taux
        $totalTests = $testStats->total ?? 0;
        $completedTests = $testStats->completed ?? 0;
        $timeoutTests = $testStats->timeout ?? 0;
        $totalEmails = $emailStats->total_emails ?? 0;
        $inboxCount = $emailStats->inbox_count ?? 0;
        $spamCount = $emailStats->spam_count ?? 0;
        
        $completionRate = $totalTests > 0 ? round(($completedTests / $totalTests) * 100, 2) : 0;
        $timeoutRate = $totalTests > 0 ? round(($timeoutTests / $totalTests) * 100, 2) : 0;
        $inboxRate = $totalEmails > 0 ? round(($inboxCount / $totalEmails) * 100, 2) : 0;
        $spamRate = $totalEmails > 0 ? round(($spamCount / $totalEmails) * 100, 2) : 0;
        
        // Préparer les données pour l'insertion
        $metricsData = [
            'metric_date' => $date->format('Y-m-d'),
            'total_tests' => $totalTests,
            'completed_tests' => $completedTests,
            'pending_tests' => $testStats->pending ?? 0,
            'in_progress_tests' => $testStats->in_progress ?? 0,
            'timeout_tests' => $timeoutTests,
            'inbox_count' => $inboxCount,
            'spam_count' => $spamCount,
            'total_emails' => $totalEmails,
            'inbox_rate' => $inboxRate,
            'spam_rate' => $spamRate,
            'completion_rate' => $completionRate,
            'timeout_rate' => $timeoutRate,
            'unique_visitors' => $uniqueVisitors,
            'provider_stats' => json_encode($providerStats),
            'audience_stats' => json_encode($audienceStats),
            'hourly_distribution' => json_encode($hourlyDistribution),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        
        // Afficher le résumé
        $this->info("");
        $this->info("📈 Résumé des métriques:");
        $this->line("  - Tests totaux: {$totalTests}");
        $this->line("  - Tests complétés: {$completedTests} ({$completionRate}%)");
        $this->line("  - Tests timeout: {$timeoutTests} ({$timeoutRate}%)");
        $this->line("  - Emails totaux: {$totalEmails}");
        $this->line("  - Inbox: {$inboxCount} ({$inboxRate}%)");
        $this->line("  - Spam: {$spamCount} ({$spamRate}%)");
        $this->line("  - Visiteurs uniques: {$uniqueVisitors}");
        
        // Enregistrer dans la base de données
        if ($existingMetrics) {
            DB::table('test_metrics_history')
                ->where('metric_date', $date->format('Y-m-d'))
                ->update($metricsData);
            $this->info("");
            $this->info("✅ Métriques mises à jour avec succès!");
        } else {
            DB::table('test_metrics_history')->insert($metricsData);
            $this->info("");
            $this->info("✅ Métriques archivées avec succès!");
        }
        
        return 0;
    }
}
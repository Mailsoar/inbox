<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Test;
use App\Models\TestResult;
use Carbon\Carbon;

class CleanOldTestsCommand extends Command
{
    protected $signature = 'tests:clean 
                            {--force : Force deletion without confirmation}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean old tests based on TEST_RETENTION_DAYS setting';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        // Récupérer la configuration de rétention
        $testRetentionDays = config('mailsoar.test_retention_days', 7);
        $cutoffDate = Carbon::now()->subDays($testRetentionDays);
        
        // Archiver les métriques du jour avant de supprimer
        if (!$dryRun) {
            $this->info("📊 Archivage des métriques avant suppression...");
            $this->call('metrics:archive', ['--date' => Carbon::today()->format('Y-m-d'), '--force' => true]);
            $this->info("");
        }
        
        $this->info("===========================================");
        $this->info("🧹 Nettoyage des tests anciens");
        $this->info("===========================================");
        $this->info("Configuration:");
        $this->info("- Rétention des tests: {$testRetentionDays} jours");
        $this->info("- Date limite: {$cutoffDate->format('Y-m-d H:i:s')}");
        $this->info("");
        
        // Compter les tests à supprimer (basé sur created_at)
        $oldTests = Test::where('created_at', '<', $cutoffDate)->count();
        
        if ($oldTests === 0) {
            $this->info("✅ Aucun test à nettoyer.");
            return 0;
        }
        
        // Afficher le résumé
        $this->warn("Tests à supprimer: {$oldTests}");
        
        if ($dryRun) {
            $this->line("");
            $this->warn("🔍 MODE DRY-RUN - Aucune suppression effectuée");
            
            // Afficher quelques exemples
            $this->line("");
            $this->info("Exemples de tests qui seraient supprimés:");
            $sampleTests = Test::where('created_at', '<', $cutoffDate)
                ->limit(5)
                ->get(['unique_id', 'visitor_email', 'created_at', 'status']);
                
            if ($sampleTests->count() > 0) {
                $this->table(
                    ['ID', 'Email', 'Créé', 'Statut'],
                    $sampleTests->map(fn($t) => [
                        $t->unique_id,
                        substr($t->visitor_email, 0, 30) . '...',
                        $t->created_at->format('Y-m-d H:i'),
                        $t->status
                    ])
                );
            }
            
            return 0;
        }
        
        // Demander confirmation si pas de --force
        if (!$force) {
            $this->line("");
            if (!$this->confirm("⚠️  Voulez-vous vraiment supprimer {$oldTests} tests ?")) {
                $this->info("Annulé.");
                return 0;
            }
        }
        
        $this->line("");
        $this->info("🗑️  Suppression en cours...");
        
        // Utiliser une transaction pour la cohérence
        DB::beginTransaction();
        
        try {
            // Récupérer les IDs des tests à supprimer
            $testIds = Test::where('created_at', '<', $cutoffDate)->pluck('id');
            
            // Supprimer les relations test_email_accounts
            $deletedRelations = DB::table('test_email_accounts')
                ->whereIn('test_id', $testIds)
                ->delete();
                
            // Supprimer les received_emails liés
            $deletedEmails = TestResult::whereIn('test_id', $testIds)->delete();
            
            // Supprimer les tests
            $deletedTests = Test::whereIn('id', $testIds)->delete();
            
            DB::commit();
            
            $this->line("");
            $this->info("✅ Nettoyage terminé avec succès!");
            $this->line("   - Tests supprimés: {$deletedTests}");
            $this->line("   - Emails supprimés: {$deletedEmails}");
            $this->line("   - Relations supprimées: {$deletedRelations}");
            
            // Log l'opération
            \Log::info('Test cleanup completed', [
                'deleted_tests' => $deletedTests,
                'deleted_emails' => $deletedEmails,
                'deleted_relations' => $deletedRelations,
                'retention_days' => $testRetentionDays,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            $this->error("❌ Erreur lors du nettoyage: " . $e->getMessage());
            \Log::error('Test cleanup failed', ['error' => $e->getMessage()]);
            return 1;
        }
        
        return 0;
    }
}
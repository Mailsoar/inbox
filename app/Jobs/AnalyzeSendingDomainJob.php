<?php

namespace App\Jobs;

use App\Models\Test;
use App\Services\SendingDomainAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Analyse l'authentification du domaine d'envoi, une fois le premier email reçu.
 *
 * Hors du chemin de traitement des emails : les requêtes DNS et les deux
 * récupérations HTTP (logo BIMI, certificat) ne doivent pas rallonger le
 * relevé des boîtes, qui est le chemin critique du démon.
 */
class AnalyzeSendingDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public int $testId)
    {
        $this->onQueue('domain-analysis');
    }

    public function handle(SendingDomainAnalyzer $analyzer): void
    {
        $test = Test::with('results')->find($this->testId);

        if (! $test) {
            return;
        }

        // Un seul passage par test, même si plusieurs emails arrivent de front.
        if ($test->domain_analyzed_at !== null) {
            return;
        }

        if ($test->results->isEmpty()) {
            return;
        }

        try {
            $analysis = $analyzer->analyze($test);
        } catch (\Throwable $e) {
            // Un diagnostic raté ne doit pas priver le visiteur de ses résultats.
            Log::warning('[AnalyzeSendingDomain] Analyse en échec', [
                'test_id' => $test->unique_id,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $test->forceFill([
            'sending_domain' => $analysis['domain'] ?? null,
            'domain_analysis' => $analysis,
            'domain_analyzed_at' => now(),
        ])->save();

        Log::info('[AnalyzeSendingDomain] Analyse enregistrée', [
            'test_id' => $test->unique_id,
            'domain' => $analysis['domain'] ?? null,
        ]);
    }
}

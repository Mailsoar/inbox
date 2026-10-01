<?php

namespace App\Jobs;

use App\Models\Test;
use App\Services\SpamAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Alerte Slack de fin de test, hors du chemin de traitement des emails :
 * une panne de Slack ne doit jamais ralentir ni faire échouer le relevé.
 */
class SendSpamAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [60, 300];

    public function __construct(public int $testId)
    {
        $this->onQueue('alerts');
    }

    public function handle(SpamAlertService $alerts): void
    {
        $test = Test::find($this->testId);

        if (! $test) {
            return;
        }

        $outcome = $alerts->handle($test);

        Log::info('[SpamAlert] Test évalué', ['test_id' => $test->unique_id, 'outcome' => $outcome]);
    }
}

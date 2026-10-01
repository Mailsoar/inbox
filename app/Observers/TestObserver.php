<?php

namespace App\Observers;

use App\Jobs\SendSpamAlertJob;
use App\Models\Test;

class TestObserver
{
    /**
     * Le statut passe à « terminé » depuis plusieurs chemins de traitement ;
     * les observer tous ici évite de devoir penser à l'alerte dans chacun.
     */
    public function updated(Test $test): void
    {
        if ($test->wasChanged('status')
            && in_array($test->status, ['completed', 'timeout'], true)
            && ! $test->spam_alert_sent_at) {
            SendSpamAlertJob::dispatch($test->id);
        }
    }
}

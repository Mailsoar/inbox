<?php

namespace App\Console\Commands;

use App\Models\Test;
use App\Services\SpamAlertService;
use Illuminate\Console\Command;

class SendSpamAlertCommand extends Command
{
    protected $signature = 'alerts:spam
                            {unique_id : Identifiant public du test (ex. MS-P5UH1J)}
                            {--force : Envoie même sous le seuil, sans rien enregistrer sur le test}';

    protected $description = "Évalue un test et envoie l'alerte Slack de fort taux de spam si besoin";

    public function handle(SpamAlertService $alerts): int
    {
        $test = Test::where('unique_id', $this->argument('unique_id'))->first();

        if (! $test) {
            $this->error('Test introuvable.');

            return self::FAILURE;
        }

        $this->info('Issue : ' . $alerts->handle($test, (bool) $this->option('force')));

        return self::SUCCESS;
    }
}

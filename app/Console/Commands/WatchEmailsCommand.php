<?php

namespace App\Console\Commands;

use App\Models\Test;
use App\Services\OptimizedEmailCheckService;
use Illuminate\Console\Command;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Boucle de surveillance des boîtes de test.
 *
 * Remplace l'attente du tic d'une minute du cron : tant qu'un test est actif,
 * les boîtes sont relevées à cadence rapprochée, puis de plus en plus espacée.
 *
 * La cadence est adaptative parce que les fournisseurs plafonnent le nombre de
 * connexions par heure (60 par compte ici) : une fréquence fixe élevée
 * épuiserait le quota en quelques minutes et bloquerait le reste de l'heure.
 * Les emails arrivant en pratique dans les 90 premières secondes, on concentre
 * l'effort là où il sert.
 */
class WatchEmailsCommand extends Command
{
    protected $signature = 'emails:watch
                            {--workers=8 : Relevés menés en parallèle (plafonné au nombre de boîtes à relever)}
                            {--idle-sleep=20 : Secondes de pause quand aucun test n\'est actif}
                            {--max-runtime=3600 : Durée de vie du processus avant redémarrage}
                            {--max-memory=256 : Mémoire (Mo) au-delà de laquelle le processus se recycle}';

    protected $description = 'Surveille les boîtes de test en continu et détecte les emails sans attendre le cron';

    /**
     * Paliers de cadence : au-delà de N secondes depuis la création du test le
     * plus récent, on relève toutes les M secondes.
     *
     * @var array<int, array{0:int, 1:int}> [âge max en secondes, intervalle]
     */
    private const CADENCE = [
        [120, 10],   // 2 premières minutes : toutes les 10 s
        [300, 30],   // jusqu'à 5 minutes    : toutes les 30 s
        [PHP_INT_MAX, 60], // au-delà        : une fois par minute
    ];

    /**
     * Files traitées par la boucle : le relevé des boîtes, et le diagnostic du
     * domaine d'envoi déclenché au premier email reçu, et les alertes Slack
     * de fin de test.
     */
    private const QUEUES = ['email-addresses', 'domain-analysis', 'alerts'];

    private bool $shouldStop = false;

    public function __construct(private OptimizedEmailCheckService $emailService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->listenForSignals();

        $startedAt = time();
        $maxRuntime = (int) $this->option('max-runtime');
        $maxMemory = (int) $this->option('max-memory') * 1024 * 1024;
        $idleSleep = (int) $this->option('idle-sleep');

        $this->info('emails:watch démarré (pid ' . getmypid() . ')');
        Log::info('[EmailWatch] Started', ['pid' => getmypid()]);

        $lastRun = 0;

        while (! $this->shouldStop) {
            // Recyclage volontaire : Supervisor relance un processus neuf.
            if ((time() - $startedAt) >= $maxRuntime || memory_get_usage(true) >= $maxMemory) {
                $this->info('Recyclage du processus (durée ou mémoire atteinte)');
                Log::info('[EmailWatch] Recycling', [
                    'runtime' => time() - $startedAt,
                    'memory_mb' => round(memory_get_usage(true) / 1048576),
                ]);
                break;
            }

            $youngest = $this->youngestActiveTestAge();

            if ($youngest === null) {
                // Plus aucun test actif, mais la clôture du dernier a pu créer
                // des jobs (alerte de fin de test) : ils partent sans attendre
                // le prochain test.
                try {
                    $pending = $this->pendingJobs();

                    if ($pending > 0) {
                        $this->drainQueue($pending);
                    }
                } catch (\Throwable $e) {
                    Log::error('[EmailWatch] Idle drain failed', ['error' => $e->getMessage()]);
                }

                $this->sleepInterruptible($idleSleep);
                continue;
            }

            $interval = $this->intervalFor($youngest);

            if ((time() - $lastRun) < $interval) {
                $this->sleepInterruptible(2);
                continue;
            }

            $lastRun = time();
            $this->runCycle($interval);
        }

        $this->info('emails:watch arrêté proprement');
        Log::info('[EmailWatch] Stopped', ['pid' => getmypid()]);

        return self::SUCCESS;
    }

    /** Âge, en secondes, du test actif le plus récent. null si aucun. */
    private function youngestActiveTestAge(): ?int
    {
        $createdAt = Test::whereIn('status', ['pending', 'in_progress'])
            ->where('timeout_at', '>', now())
            ->max('created_at');

        return $createdAt === null ? null : max(0, now()->diffInSeconds($createdAt));
    }

    private function intervalFor(int $age): int
    {
        foreach (self::CADENCE as [$maxAge, $interval]) {
            if ($age <= $maxAge) {
                return $interval;
            }
        }

        return 60;
    }

    /** Un relevé : on dispatche les vérifications, puis on vide la file. */
    private function runCycle(int $interval): void
    {
        try {
            $stats = $this->emailService->processPendingChecks();

            // On vide la file dès qu'elle contient du travail prêt, et pas
            // seulement après un dispatch : le service déduplique, donc un
            // job déjà en attente ne serait jamais traité autrement.
            $pending = $this->pendingJobs();

            $workers = 0;
            $elapsed = 0;

            if ($pending > 0) {
                $started = microtime(true);
                $workers = $this->drainQueue($pending);
                $elapsed = (int) round(microtime(true) - $started);
            }

            $this->line(sprintf(
                '[%s] cadence %ds — %d dispatché(s), %d traité(s) par %d worker(s) en %ds',
                now()->format('H:i:s'),
                $interval,
                $stats['dispatched'] ?? 0,
                $pending,
                $workers,
                $elapsed
            ));
        } catch (\Throwable $e) {
            // Une erreur de relevé ne doit jamais tuer la boucle.
            $this->error('Cycle en échec : ' . $e->getMessage());
            Log::error('[EmailWatch] Cycle failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->sleepInterruptible(5);
        }
    }

    /** Jobs prêts à être traités dans les files du démon. */
    private function pendingJobs(): int
    {
        return \DB::table('jobs')
            ->whereIn('queue', self::QUEUES)
            ->where('available_at', '<=', now()->timestamp)
            ->count();
    }

    /**
     * Vide la file avec plusieurs workers menés de front.
     *
     * Chaque relevé de boîte prend quelques secondes, essentiellement passées à
     * attendre le serveur IMAP : les mener en parallèle divise d'autant la durée
     * du balayage. PostgreSQL verrouille les jobs en « FOR UPDATE SKIP LOCKED »,
     * donc deux workers ne peuvent pas prendre le même.
     *
     * @return int Nombre de workers réellement lancés
     */
    private function drainQueue(int $pending): int
    {
        // Inutile de lancer plus de workers que de jobs à traiter.
        $workers = max(1, min((int) $this->option('workers'), $pending));

        $pool = Process::pool(function (Pool $pool) use ($workers) {
            for ($i = 0; $i < $workers; $i++) {
                $pool->path(base_path())
                    ->timeout(180)
                    ->command([
                        PHP_BINARY, 'artisan', 'queue:work',
                        '--queue=' . implode(',', self::QUEUES),
                        '--stop-when-empty',
                        '--tries=1',
                        '--max-time=120',
                    ]);
            }
        })->start();

        foreach ($pool->wait() as $index => $result) {
            if (! $result->successful()) {
                Log::warning('[EmailWatch] Worker en échec', [
                    'worker' => $index,
                    'exit_code' => $result->exitCode(),
                    'error' => mb_substr($result->errorOutput(), 0, 500),
                ]);
            }
        }

        return $workers;
    }

    /** Pause fractionnée, pour réagir vite à une demande d'arrêt. */
    private function sleepInterruptible(int $seconds): void
    {
        for ($i = 0; $i < $seconds && ! $this->shouldStop; $i++) {
            sleep(1);
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    private function listenForSignals(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGTERM, SIGINT, SIGQUIT] as $signal) {
            pcntl_signal($signal, function () {
                $this->shouldStop = true;
            });
        }
    }
}

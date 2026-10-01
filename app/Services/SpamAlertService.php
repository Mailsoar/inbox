<?php

namespace App\Services;

use App\Models\Test;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Signale dans Slack les tests dont une forte part des emails est arrivée en
 * spam, pour que l'équipe puisse recontacter le visiteur à la main.
 */
class SpamAlertService
{
    public function __construct(private ComplianceScoreService $compliance)
    {
    }

    /**
     * Envoie l'alerte si le test la justifie, et renvoie l'issue sous forme
     * de code court (journalisé, et affiché par la commande artisan).
     *
     * $force ignore les seuils, les exclusions et le dédoublonnage, sans rien
     * enregistrer sur le test : il sert à vérifier le rendu du message.
     */
    public function handle(Test $test, bool $force = false): string
    {
        if (! $force && ! config('mailsoar.spam_alert.enabled')) {
            return 'disabled';
        }

        if (! config('services.slack_feedback.bot_token') || ! config('services.slack_feedback.channel_id')) {
            return 'not_configured';
        }

        if (! $force && $test->spam_alert_sent_at) {
            return 'already_sent';
        }

        if (! $force && $this->isExcluded($test)) {
            return 'excluded';
        }

        $test->load(['results', 'emailAccounts.emailProvider']);

        $received = $test->results->count();
        $spam = $test->results->where('placement', 'spam')->count();
        $rate = $received > 0 ? $spam / $received * 100 : 0.0;

        if (! $force) {
            if ($received < (int) config('mailsoar.spam_alert.min_received')) {
                return 'not_enough_results';
            }

            if ($rate < (float) config('mailsoar.spam_alert.threshold')) {
                return 'below_threshold';
            }

            // Réservation atomique : deux workers ne peuvent pas alerter
            // pour le même test.
            $claimed = Test::whereKey($test->id)
                ->whereNull('spam_alert_sent_at')
                ->update(['spam_alert_sent_at' => now()]);

            if (! $claimed) {
                return 'already_sent';
            }
        }

        try {
            $thread = $force ? null : $this->openThreadFor($test);
            $ts = $this->post($this->message($test, $spam, $received, $rate, (bool) $thread), $thread);
        } catch (Throwable $e) {
            // Rend la main pour que la nouvelle tentative du job puisse réessayer.
            if (! $force) {
                Test::whereKey($test->id)->update(['spam_alert_sent_at' => null]);
            }

            throw $e;
        }

        if (! $force) {
            // Les réponses gardent l'horodatage du message d'origine : c'est
            // lui que les tests suivants du domaine retrouvent.
            Test::whereKey($test->id)->update(['spam_alert_slack_ts' => $thread ?? $ts]);
        }

        Log::info('[SpamAlert] Alerte envoyée', [
            'test_id' => $test->unique_id,
            'spam' => $spam,
            'received' => $received,
            'threaded' => (bool) $thread,
            'forced' => $force,
        ]);

        return $thread ? 'sent_in_thread' : 'sent';
    }

    /** Domaine qui identifie le prospect : celui d'envoi, à défaut celui du visiteur. */
    private function domainOf(Test $test): string
    {
        return strtolower($test->sending_domain ?: substr(strrchr($test->visitor_email, '@'), 1));
    }

    private function isExcluded(Test $test): bool
    {
        $excluded = array_filter(array_map(
            fn ($d) => strtolower(trim($d)),
            explode(',', (string) config('mailsoar.spam_alert.excluded_domains'))
        ));

        $domains = [$this->domainOf($test), strtolower(substr(strrchr($test->visitor_email, '@'), 1))];

        foreach ($domains as $domain) {
            foreach ($excluded as $ex) {
                if ($domain === $ex || str_ends_with($domain, '.' . $ex)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Fil Slack d'une alerte récente pour le même domaine : un prospect qui
     * relance le test y voit l'évolution, sans nouveau message dans le canal.
     */
    private function openThreadFor(Test $test): ?string
    {
        $domain = $this->domainOf($test);

        return Test::whereKeyNot($test->id)
            ->whereNotNull('spam_alert_slack_ts')
            ->where('spam_alert_sent_at', '>=', now()->subDays((int) config('mailsoar.spam_alert.thread_days')))
            ->where(fn ($q) => $q->where('sending_domain', $domain)
                ->orWhere('visitor_email', 'like', '%@' . $domain))
            ->latest('spam_alert_sent_at')
            ->value('spam_alert_slack_ts');
    }

    /** Publie le message et renvoie son horodatage Slack. */
    private function post(array $message, ?string $threadTs): string
    {
        $response = Http::withToken(config('services.slack_feedback.bot_token'))
            ->timeout(10)
            ->post('https://slack.com/api/chat.postMessage', array_filter([
                'channel' => config('services.slack_feedback.channel_id'),
                'thread_ts' => $threadTs,
                'unfurl_links' => false,
                ...$message,
            ], fn ($v) => $v !== null))
            ->throw();

        if (! $response->json('ok')) {
            throw new RuntimeException('Slack chat.postMessage: ' . $response->json('error', 'unknown_error'));
        }

        return (string) $response->json('ts');
    }

    private function message(Test $test, int $spam, int $received, float $rate, bool $isFollowUp): array
    {
        $analysis = $this->compliance->analyze($test);
        $domain = $this->domainOf($test);
        $total = $test->emailAccounts->count();
        $inbox = $test->results->whereIn('placement', Test::getInboxPlacements())->count();
        $other = $received - $spam - $inbox;
        $missing = max(0, $total - $received);
        $pct = (int) round($rate);

        $title = $isFollowUp
            ? "Nouveau test : {$pct} % en spam"
            : "🚨 {$pct} % en spam — {$domain}";

        $placement = "*{$spam} spam* / {$received} reçus (sur {$total} boîtes)\n"
            . "{$inbox} inbox · {$other} autres onglets · {$missing} jamais arrivés";

        $score = $analysis['score'] !== null
            ? "{$analysis['score']}/100 ({$analysis['grade']})"
            : '—';

        $icons = ['pass' => '✅', 'partial' => '⚠️', 'fail' => '❌', 'none' => '➖'];
        $auth = collect(['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC'])
            ->map(fn ($label, $key) => $label . ' ' . ($icons[$analysis['checks'][$key]['status'] ?? 'none'] ?? '➖'))
            ->implode('  ');

        if (($test->domain_analysis['dmarc']['policy'] ?? null) === 'none') {
            $auth .= "\nDMARC en p=none";
        }

        $providers = collect($analysis['providers'])
            ->map(function ($p) {
                $label = ucfirst($p['provider']) . ' (' . strtoupper($p['audience']) . ')';
                $missing = $p['total'] - $p['received'];
                $parts = array_filter([
                    $p['spam'] ? "*{$p['spam']} spam*" : null,
                    $p['inbox'] ? "{$p['inbox']} inbox" : null,
                    $p['other'] ? "{$p['other']} autres" : null,
                    $missing ? "{$missing} manquant" . ($missing > 1 ? 's' : '') : null,
                ]);

                return "• {$label} : " . implode(' · ', $parts) . " / {$p['total']}";
            })
            ->take(20)
            ->implode("\n");

        $status = ['completed' => 'terminé', 'timeout' => 'terminé (délai écoulé)', 'cancelled' => 'annulé'][$test->status] ?? $test->status;

        return [
            'text' => "{$pct} % en spam pour {$domain} ({$test->visitor_email})",
            'blocks' => [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => mb_substr($title, 0, 150)]],
                ['type' => 'section', 'fields' => [
                    ['type' => 'mrkdwn', 'text' => "*Email*\n" . $this->escape($test->visitor_email)],
                    ['type' => 'mrkdwn', 'text' => "*Domaine d'envoi*\n" . $this->escape($test->sending_domain ?: '—')],
                    ['type' => 'mrkdwn', 'text' => "*Placement*\n{$placement}"],
                    ['type' => 'mrkdwn', 'text' => "*Score*\n{$score}"],
                    ['type' => 'mrkdwn', 'text' => "*Authentification*\n{$auth}"],
                    ['type' => 'mrkdwn', 'text' => "*Opt-in marketing*\n" . ($test->marketing_consent ? 'Oui' : 'Non')],
                ]],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "*Par fournisseur*\n" . ($providers ?: '—')]],
                ['type' => 'context', 'elements' => [
                    ['type' => 'mrkdwn', 'text' => "Test {$test->unique_id} · {$status} · lancé " . $test->created_at->locale('fr')->diffForHumans()],
                ]],
                ['type' => 'actions', 'elements' => [
                    ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => "Voir dans l'admin"], 'url' => route('admin.tests.show', $test), 'style' => 'primary'],
                    ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'Rapport du visiteur'], 'url' => route('test.results', ['unique_id' => $test->unique_id])],
                ]],
            ],
        ];
    }

    /** Échappement imposé par Slack pour le texte mrkdwn. */
    private function escape(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }
}

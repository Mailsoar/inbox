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
    public function __construct(
        private ComplianceScoreService $compliance,
        private HubSpotLeadService $hubspot,
    ) {
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

        $analysis = $this->compliance->analyze($test);
        $crm = $force ? null : $this->syncHubSpot($test, $rate, $analysis);

        try {
            $thread = $force ? null : $this->openThreadFor($test);
            $ts = $this->post($this->message($test, $analysis, $spam, $received, $rate, (bool) $thread, $crm), $thread);
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

    /**
     * Crée ou met à jour le lead HubSpot, une seule fois par test même si
     * l'envoi Slack est retenté. Un échec HubSpot n'empêche pas l'alerte :
     * il est signalé dans le message pour une saisie manuelle.
     *
     * @return array{id:string, owner:?string, created:?bool}|false|null
     *         Contact synchronisé, false en cas d'échec, null si HubSpot
     *         n'est pas configuré
     */
    private function syncHubSpot(Test $test, float $rate, array $analysis): array|false|null
    {
        // Déjà synchronisé lors d'une tentative précédente du job.
        if ($test->hubspot_contact_id) {
            return ['id' => $test->hubspot_contact_id, 'owner' => null, 'created' => null];
        }

        if (! $this->hubspot->isConfigured()) {
            return null;
        }

        try {
            $contact = $this->hubspot->syncSpamLead($test, [
                'spam_rate' => (int) round($rate),
                'score' => $analysis['score'],
            ]);
        } catch (Throwable $e) {
            Log::error('[SpamAlert] Synchronisation HubSpot en échec', [
                'test_id' => $test->unique_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        Test::whereKey($test->id)->update(['hubspot_contact_id' => $contact['id']]);
        $test->hubspot_contact_id = $contact['id'];

        return $contact;
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

    /**
     * Message Slack, en anglais : le canal est partagé avec l'équipe
     * commerciale anglophone.
     */
    private function message(Test $test, array $analysis, int $spam, int $received, float $rate, bool $isFollowUp, array|false|null $crm): array
    {
        $domain = $this->domainOf($test);
        $total = $test->emailAccounts->count();
        $inbox = $test->results->whereIn('placement', Test::getInboxPlacements())->count();
        $other = $received - $spam - $inbox;
        $missing = max(0, $total - $received);
        $pct = (int) round($rate);

        $title = $isFollowUp
            ? "New test: {$pct}% in spam"
            : "🚨 {$pct}% in spam — {$domain}";

        $placement = "*{$spam} spam* / {$received} received (out of {$total} inboxes)\n"
            . "{$inbox} inbox · {$other} other tabs · {$missing} never arrived";

        $score = $analysis['score'] !== null
            ? "{$analysis['score']}/100 ({$analysis['grade']})"
            : '—';

        $icons = ['pass' => '✅', 'partial' => '⚠️', 'fail' => '❌', 'none' => '➖'];
        $auth = collect(['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC'])
            ->map(fn ($label, $key) => $label . ' ' . ($icons[$analysis['checks'][$key]['status'] ?? 'none'] ?? '➖'))
            ->implode('  ');

        if (($test->domain_analysis['dmarc']['policy'] ?? null) === 'none') {
            $auth .= "\nDMARC policy is p=none";
        }

        $providers = collect($analysis['providers'])
            ->map(function ($p) {
                $label = ucfirst($p['provider']) . ' (' . strtoupper($p['audience']) . ')';
                $missing = $p['total'] - $p['received'];
                $parts = array_filter([
                    $p['spam'] ? "*{$p['spam']} spam*" : null,
                    $p['inbox'] ? "{$p['inbox']} inbox" : null,
                    $p['other'] ? "{$p['other']} other" : null,
                    $missing ? "{$missing} missing" : null,
                ]);

                return "• {$label}: " . implode(' · ', $parts) . " / {$p['total']}";
            })
            ->take(20)
            ->implode("\n");

        $status = ['completed' => 'completed', 'timeout' => 'completed (timed out)', 'cancelled' => 'cancelled'][$test->status] ?? $test->status;
        $language = $test->language === 'fr' ? 'French' : 'English';

        $crmNote = match (true) {
            $crm === false => '⚠️ HubSpot sync failed: add the lead manually',
            ! is_array($crm) => null,
            $crm['created'] === true => 'HubSpot: new lead assigned to ' . ($crm['owner'] ?? 'its owner'),
            $crm['owner'] !== null => "HubSpot: existing contact, owned by {$crm['owner']}",
            default => 'HubSpot: existing contact updated',
        };

        $buttons = [
            ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'Open in admin'], 'url' => route('admin.tests.show', $test), 'style' => 'primary'],
            ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'Visitor report'], 'url' => route('test.results', ['unique_id' => $test->unique_id])],
        ];

        if (is_array($crm)) {
            $buttons[] = ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'HubSpot contact'], 'url' => $this->hubspot->contactUrl($crm['id'])];
        }

        return [
            'text' => "{$pct}% in spam for {$domain} ({$test->visitor_email})",
            'blocks' => [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => mb_substr($title, 0, 150)]],
                ['type' => 'section', 'fields' => [
                    ['type' => 'mrkdwn', 'text' => "*Email*\n" . $this->escape($test->visitor_email)],
                    ['type' => 'mrkdwn', 'text' => "*Sending domain*\n" . $this->escape($test->sending_domain ?: '—')],
                    ['type' => 'mrkdwn', 'text' => "*Placement*\n{$placement}"],
                    ['type' => 'mrkdwn', 'text' => "*Score*\n{$score}"],
                    ['type' => 'mrkdwn', 'text' => "*Authentication*\n{$auth}"],
                    ['type' => 'mrkdwn', 'text' => "*Agreed to be contacted*\n" . ($test->marketing_consent ? 'Yes' : 'No')],
                    ['type' => 'mrkdwn', 'text' => "*Language*\n{$language}"],
                ]],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "*By provider*\n" . ($providers ?: '—')]],
                ['type' => 'context', 'elements' => [
                    ['type' => 'mrkdwn', 'text' => "Test {$test->unique_id} · {$status} · started " . $test->created_at->locale('en')->diffForHumans()
                        . ($crmNote ? " · {$crmNote}" : '')],
                ]],
                ['type' => 'actions', 'elements' => $buttons],
            ],
        ];
    }

    /** Échappement imposé par Slack pour le texte mrkdwn. */
    private function escape(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }
}

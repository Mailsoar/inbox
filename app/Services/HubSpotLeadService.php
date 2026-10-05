<?php

namespace App\Services;

use App\Models\Test;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Crée ou met à jour dans HubSpot le contact d'un visiteur dont le test a
 * déclenché une alerte spam.
 *
 * Les propriétés du groupe « MailSoar Inbox Test » reflètent toujours le
 * dernier test en alerte. Le reste de la fiche n'est rempli que s'il est vide,
 * pour ne jamais défaire ce que l'équipe commerciale a déjà qualifié.
 */
class HubSpotLeadService
{
    private const BASE = 'https://api.hubapi.com/crm/v3/objects/contacts';

    private const CONSENT = 'Freely given consent from contact';
    private const LEGITIMATE_INTEREST = 'Legitimate interest – prospect/lead';

    private const READ = [
        'source', 'lead_source__drilldown_contact_level', 'hubspot_owner_id',
        'lifecyclestage', 'hs_lead_status', 'hs_legal_basis',
        'mailsoar_marketing_consent', 'mailsoar_test_alert_count', 'hs_language',
    ];

    public function isConfigured(): bool
    {
        return (bool) config('services.hubspot.access_token');
    }

    /**
     * @param array{spam_rate:int, score:?int} $stats
     * @return array{id:string, owner:?string, created:bool} Contact et nom
     *         de son propriétaire, quand c'est l'un de ceux qu'on attribue
     */
    public function syncSpamLead(Test $test, array $stats, bool $retried = false): array
    {
        $existing = $this->find($test->visitor_email);
        $current = $existing['properties'] ?? [];

        $properties = $this->properties($test, $stats, $current);
        $owner = $this->ownerName($properties['hubspot_owner_id'] ?? $current['hubspot_owner_id'] ?? null);

        if ($existing) {
            $this->client()->patch(self::BASE . '/' . $existing['id'], ['properties' => $properties])->throw();

            return ['id' => (string) $existing['id'], 'owner' => $owner, 'created' => false];
        }

        $response = $this->client()->post(self::BASE, [
            'properties' => ['email' => $test->visitor_email] + $properties,
        ]);

        // Créé entre-temps par un autre processus : on met à jour la fiche.
        if ($response->status() === 409 && ! $retried) {
            return $this->syncSpamLead($test, $stats, true);
        }

        return ['id' => (string) $response->throw()->json('id'), 'owner' => $owner, 'created' => true];
    }

    /** Propriétaire attribué selon la langue du test : français → Pierre, sinon Larry. */
    private function ownerFor(Test $test): array
    {
        $owners = config('services.hubspot.lead_owners');

        return $owners[$this->languageOf($test)] ?? $owners['en'];
    }

    private function ownerName(?string $ownerId): ?string
    {
        return collect(config('services.hubspot.lead_owners'))->firstWhere('id', $ownerId)['name'] ?? null;
    }

    private function languageOf(Test $test): string
    {
        return $test->language === 'fr' ? 'fr' : 'en';
    }

    public function contactUrl(string $contactId): string
    {
        return sprintf(
            'https://%s/contacts/%s/record/0-1/%s',
            config('services.hubspot.ui_domain'),
            config('services.hubspot.portal_id'),
            $contactId
        );
    }

    private function properties(Test $test, array $stats, array $current): array
    {
        $properties = [
            'mailsoar_test_spam_rate' => $stats['spam_rate'],
            'mailsoar_test_score' => $stats['score'] ?? '',
            'mailsoar_test_sending_domain' => $test->sending_domain ?? '',
            'mailsoar_test_alerted_at' => now()->getTimestampMs(),
            'mailsoar_test_alert_count' => (int) ($current['mailsoar_test_alert_count'] ?? 0) + 1,
            'mailsoar_test_report_url' => route('test.results', ['unique_id' => $test->unique_id]),
            'mailsoar_test_admin_url' => route('admin.tests.show', $test),
            'mailsoar_test_language' => $this->languageOf($test),
        ];

        $fillIfEmpty = [
            'source' => 'Mailsoar Tool',
            'lead_source__drilldown_contact_level' => 'MailSoar Inbox Test',
            'hubspot_owner_id' => $this->ownerFor($test)['id'],
            'hs_lead_status' => 'NEW',
            'hs_language' => $this->languageOf($test),
        ];

        foreach ($fillIfEmpty as $name => $value) {
            if (empty($current[$name]) && $value) {
                $properties[$name] = $value;
            }
        }

        // L'étape de cycle de vie ne recule jamais : seul un simple abonné
        // (ou une fiche sans étape) devient lead.
        if (in_array($current['lifecyclestage'] ?? '', ['', 'subscriber'], true)) {
            $properties['lifecyclestage'] = 'lead';
        }

        // Le consentement ne se retire pas : « non » ne remplace jamais « oui ».
        if ($test->marketing_consent) {
            $properties['mailsoar_marketing_consent'] = 'true';

            if (in_array($current['hs_legal_basis'] ?? '', ['', self::LEGITIMATE_INTEREST], true)) {
                $properties['hs_legal_basis'] = self::CONSENT;
            }
        } else {
            if (($current['mailsoar_marketing_consent'] ?? '') === '') {
                $properties['mailsoar_marketing_consent'] = 'false';
            }

            if (empty($current['hs_legal_basis'])) {
                $properties['hs_legal_basis'] = self::LEGITIMATE_INTEREST;
            }
        }

        return $properties;
    }

    /** Recherche par email, à jour immédiatement contrairement à l'API search. */
    private function find(string $email): ?array
    {
        $response = $this->client()->get(self::BASE . '/' . rawurlencode($email), [
            'idProperty' => 'email',
            'properties' => implode(',', self::READ),
        ]);

        if ($response->status() === 404) {
            return null;
        }

        return $response->throw()->json();
    }

    private function client(): PendingRequest
    {
        return Http::withToken(config('services.hubspot.access_token'))
            ->acceptJson()
            ->timeout(10);
    }
}

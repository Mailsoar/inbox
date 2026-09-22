<?php

namespace App\Services;

use App\Models\Test;
use App\Services\DomainAuthentication\BimiAnalyzer;
use App\Services\DomainAuthentication\DkimAnalyzer;
use App\Services\DomainAuthentication\DmarcAnalyzer;
use App\Services\DomainAuthentication\DnsResolver;
use App\Services\DomainAuthentication\SpfAnalyzer;

/**
 * Diagnostic complet de l'authentification du domaine d'envoi d'un test.
 *
 * Le domaine et les sélecteurs DKIM se lisent dans les en-têtes du message
 * effectivement reçu : c'est la seule source fiable, aucune énumération n'étant
 * possible depuis le DNS.
 */
class SendingDomainAnalyzer
{
    public function analyze(Test $test): array
    {
        $result = $test->results->first(fn ($r) => ! empty($r->raw_headers))
            ?? $test->results->first();

        if (! $result) {
            return ['domain' => null, 'analyzed_at' => now()->toIso8601String()];
        }

        $headers = (string) ($result->raw_headers ?? '');
        $signatures = $this->dkimSignatures($headers);

        $fromDomain = $this->domainOf($result->from_email);
        // Le domaine signataire fait autorité ; à défaut celui de l'expéditeur.
        $domain = $signatures[0]['d'] ?? $fromDomain;

        if (! $domain) {
            return ['domain' => null, 'analyzed_at' => now()->toIso8601String()];
        }

        // Un résolveur partagé : les mêmes hôtes reviennent d'un analyseur à l'autre.
        $dns = new DnsResolver();

        $spf = (new SpfAnalyzer($dns))->analyze($domain);
        $dkim = (new DkimAnalyzer($dns))->analyze($fromDomain ?: $domain, $signatures);
        $dmarc = (new DmarcAnalyzer($dns))->analyze($domain);
        $bimi = (new BimiAnalyzer($dns))->analyze($domain, $this->bimiSelector($headers), $dmarc);

        return [
            'domain' => $domain,
            'from_domain' => $fromDomain,
            'spf' => $spf,
            'dkim' => $dkim,
            'dmarc' => $dmarc,
            'bimi' => $bimi,
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Toutes les signatures DKIM du message, dans l'ordre des en-têtes.
     *
     * @return array<int, array{d:?string, s:?string, a:?string}>
     */
    private function dkimSignatures(string $headers): array
    {
        if (! preg_match_all('/^DKIM-Signature:(.*?)(?=\r?\n[A-Za-z-]+:|\z)/ims', $headers, $matches)) {
            return [];
        }

        $signatures = [];

        foreach ($matches[1] as $raw) {
            $flat = trim(preg_replace('/\s+/', ' ', $raw));

            $signatures[] = [
                'd' => $this->capture($flat, '/\bd=([^;\s]+)/'),
                's' => $this->capture($flat, '/\bs=([^;\s]+)/'),
                'a' => $this->capture($flat, '/\ba=([^;\s]+)/'),
            ];
        }

        return $signatures;
    }

    /** Sélecteur BIMI annoncé par le message, sinon « default ». */
    private function bimiSelector(string $headers): ?string
    {
        if (! preg_match('/^BIMI-Selector:(.*?)$/im', $headers, $m)) {
            return null;
        }

        return $this->capture(trim($m[1]), '/\bs=([^;\s]+)/');
    }

    private function capture(string $subject, string $pattern): ?string
    {
        return preg_match($pattern, $subject, $m) ? strtolower(trim($m[1])) : null;
    }

    private function domainOf(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        return strtolower(trim(substr(strrchr($email, '@'), 1)));
    }
}

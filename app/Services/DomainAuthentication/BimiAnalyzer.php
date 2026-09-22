<?php

namespace App\Services\DomainAuthentication;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analyse la configuration BIMI d'un domaine.
 *
 * Contrairement aux trois autres mécanismes, BIMI ne se vérifie pas dans le seul
 * DNS : l'enregistrement pointe vers un logo et, le plus souvent, vers un
 * certificat de marque. Les deux sont récupérés et contrôlés, car c'est là que
 * se logent la plupart des configurations qui « ne s'affichent pas » sans raison
 * apparente — à commencer par le prérequis DMARC, cause n°1.
 */
class BimiAnalyzer
{
    private const LOGOTYPE_OID = '1.3.6.1.5.5.7.1.12';
    private const MAX_LOGO_BYTES = 1048576;   // 1 Mo
    private const MAX_CERT_BYTES = 262144;    // 256 Ko
    private const RECOMMENDED_LOGO_BYTES = 32768;
    private const TIMEOUT = 5;

    public function __construct(private DnsResolver $dns)
    {
    }

    /**
     * @param array $dmarc Résultat de DmarcAnalyzer, pour le prérequis
     */
    public function analyze(string $domain, ?string $selector, array $dmarc): array
    {
        $selector = $selector ?: 'default';
        $records = $this->dns->txtStartingWith($selector . '._bimi.' . $domain, 'v=BIMI1');

        if ($records === []) {
            return [
                'record' => null,
                'selector' => $selector,
                'configured' => false,
                'findings' => [Finding::info('bimi_absent', ['domain' => $domain])->toArray()],
            ];
        }

        $findings = [];

        if (count($records) > 1) {
            $findings[] = Finding::error('bimi_multiple', ['count' => count($records)]);
        }

        $record = $records[0];
        $tags = $this->tags($record);
        $logoUrl = trim($tags['l'] ?? '');
        $certUrl = trim($tags['a'] ?? '');

        // Un enregistrement aux deux valeurs vides décline explicitement BIMI.
        if ($logoUrl === '' && $certUrl === '') {
            return [
                'record' => $record,
                'selector' => $selector,
                'configured' => false,
                'findings' => [Finding::info('bimi_declined')->toArray()],
            ];
        }

        // Sans DMARC en quarantine ou reject à 100 %, aucun logo ne s'affichera.
        if (empty($dmarc['enforced'])) {
            $findings[] = Finding::error('bimi_dmarc_not_enforced', [
                'policy' => $dmarc['policy'] ?? 'none',
                'pct' => $dmarc['pct'] ?? 100,
            ]);
        }

        $logo = $this->analyzeLogo($logoUrl, $findings);
        $certificate = $this->analyzeCertificate($certUrl, $domain, $findings);

        return [
            'record' => $record,
            'selector' => $selector,
            'configured' => true,
            'logo_url' => $logoUrl ?: null,
            'logo' => $logo,
            'certificate_url' => $certUrl ?: null,
            'certificate' => $certificate,
            'findings' => array_map(fn (Finding $f) => $f->toArray(), $findings),
        ];
    }

    /** @param array<int, Finding> $findings */
    private function analyzeLogo(string $url, array &$findings): ?array
    {
        if ($url === '') {
            $findings[] = Finding::error('bimi_no_logo');
            return null;
        }

        if (! str_starts_with(strtolower($url), 'https://')) {
            $findings[] = Finding::error('bimi_logo_not_https');
            return null;
        }

        $body = $this->fetch($url, self::MAX_LOGO_BYTES, $contentType);

        if ($body === null) {
            $findings[] = Finding::error('bimi_logo_unreachable', ['url' => $url]);
            return null;
        }

        if ($contentType && stripos($contentType, 'svg') === false) {
            $findings[] = Finding::error('bimi_logo_not_svg', ['type' => $contentType]);
        }

        $size = strlen($body);
        if ($size > self::RECOMMENDED_LOGO_BYTES) {
            $findings[] = Finding::warning('bimi_logo_heavy', ['kb' => (int) round($size / 1024)]);
        }

        // Profil SVG Tiny Portable/Secure : exigé par toutes les messageries.
        if (! preg_match('/baseProfile\s*=\s*["\']tiny-ps["\']/i', $body)) {
            $findings[] = Finding::error('bimi_logo_not_tiny_ps');
        }

        if (preg_match('/<script[\s>]/i', $body)) {
            $findings[] = Finding::error('bimi_logo_has_script');
        }

        if (preg_match('/<(animate|animateTransform|animateMotion|set)[\s>]/i', $body)) {
            $findings[] = Finding::error('bimi_logo_has_animation');
        }

        // Une référence externe casse l'affichage et pose un risque de traçage.
        if (preg_match('/(xlink:href|href)\s*=\s*["\']https?:/i', $body) || preg_match('/<image[\s>]/i', $body)) {
            $findings[] = Finding::error('bimi_logo_external_reference');
        }

        $hasTitle = (bool) preg_match('/<title[\s>]/i', $body);
        if (! $hasTitle) {
            $findings[] = Finding::warning('bimi_logo_no_title');
        }

        return [
            'size_bytes' => $size,
            'content_type' => $contentType,
            'has_title' => $hasTitle,
        ];
    }

    /** @param array<int, Finding> $findings */
    private function analyzeCertificate(string $url, string $domain, array &$findings): ?array
    {
        if ($url === '') {
            // Gmail n'affiche aucun logo sans certificat de marque.
            $findings[] = Finding::warning('bimi_no_certificate');
            return null;
        }

        if (! str_starts_with(strtolower($url), 'https://')) {
            $findings[] = Finding::error('bimi_certificate_not_https');
            return null;
        }

        $body = $this->fetch($url, self::MAX_CERT_BYTES, $contentType);

        if ($body === null) {
            $findings[] = Finding::error('bimi_certificate_unreachable', ['url' => $url]);
            return null;
        }

        // Le fichier PEM contient la chaîne ; le premier bloc est le certificat.
        if (! preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $body, $m)) {
            $findings[] = Finding::error('bimi_certificate_unreadable');
            return null;
        }

        $parsed = @openssl_x509_parse($m[0]);

        if (! $parsed) {
            $findings[] = Finding::error('bimi_certificate_unreadable');
            return null;
        }

        $validFrom = $parsed['validFrom_time_t'] ?? null;
        $validTo = $parsed['validTo_time_t'] ?? null;
        $now = time();

        if ($validTo && $validTo < $now) {
            $findings[] = Finding::error('bimi_certificate_expired', [
                'date' => date('Y-m-d', $validTo),
            ]);
        } elseif ($validTo && $validTo - $now < 30 * 86400) {
            $findings[] = Finding::warning('bimi_certificate_expiring', [
                'date' => date('Y-m-d', $validTo),
            ]);
        }

        if ($validFrom && $validFrom > $now) {
            $findings[] = Finding::error('bimi_certificate_not_yet_valid');
        }

        // Le certificat doit couvrir le domaine qui publie l'enregistrement.
        $names = $this->subjectNames($parsed);
        if ($names !== [] && ! $this->covers($names, $domain)) {
            $findings[] = Finding::error('bimi_certificate_domain_mismatch', [
                'domain' => $domain,
                'names' => implode(', ', array_slice($names, 0, 3)),
            ]);
        }

        // L'extension logotype porte le logo : sans elle, ce n'est pas un VMC.
        if (! isset($parsed['extensions'][self::LOGOTYPE_OID])
            && ! $this->hasLogotypeExtension($m[0])) {
            $findings[] = Finding::error('bimi_certificate_no_logotype');
        }

        return [
            'issuer' => $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? null),
            'subject' => $parsed['subject']['O'] ?? ($parsed['subject']['CN'] ?? null),
            'valid_from' => $validFrom ? date('Y-m-d', $validFrom) : null,
            'valid_to' => $validTo ? date('Y-m-d', $validTo) : null,
        ];
    }

    /** @return array<int, string> */
    private function subjectNames(array $parsed): array
    {
        $names = [];

        if (! empty($parsed['subject']['CN'])) {
            $names[] = strtolower($parsed['subject']['CN']);
        }

        $san = $parsed['extensions']['subjectAltName'] ?? '';
        foreach (explode(',', $san) as $entry) {
            $entry = trim($entry);
            if (stripos($entry, 'DNS:') === 0) {
                $names[] = strtolower(substr($entry, 4));
            }
        }

        return array_values(array_unique($names));
    }

    /** @param array<int, string> $names */
    private function covers(array $names, string $domain): bool
    {
        $domain = strtolower($domain);

        foreach ($names as $name) {
            if ($name === $domain) {
                return true;
            }
            if (str_starts_with($name, '*.') && str_ends_with($domain, substr($name, 1))) {
                return true;
            }
            // Un VMC émis pour le domaine racine couvre ses sous-domaines.
            if (str_ends_with($domain, '.' . $name)) {
                return true;
            }
        }

        return false;
    }

    /** Recherche l'OID logotype dans le DER, quand OpenSSL ne l'expose pas. */
    private function hasLogotypeExtension(string $pem): bool
    {
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);

        if ($der === false) {
            return false;
        }

        // OID 1.3.6.1.5.5.7.1.12 encodé en DER
        return str_contains($der, "\x06\x08\x2b\x06\x01\x05\x05\x07\x01\x0c");
    }

    /** Récupération plafonnée, une seule tentative, erreurs absorbées. */
    private function fetch(string $url, int $maxBytes, ?string &$contentType = null): ?string
    {
        $contentType = null;

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::TIMEOUT)
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $contentType = $response->header('Content-Type') ?: null;
            $body = $response->body();

            return strlen($body) > $maxBytes ? substr($body, 0, $maxBytes) : $body;
        } catch (\Throwable $e) {
            Log::debug('[BimiAnalyzer] fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /** @return array<string, string> */
    private function tags(string $record): array
    {
        $tags = [];

        foreach (explode(';', $record) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $pair, 2);
            $tags[strtolower(trim($name))] = trim($value);
        }

        return $tags;
    }
}

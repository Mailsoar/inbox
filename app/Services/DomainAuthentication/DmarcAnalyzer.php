<?php

namespace App\Services\DomainAuthentication;

use Illuminate\Support\Facades\Log;
use Pdp\Rules;

/**
 * Analyse la politique DMARC d'un domaine — RFC 7489.
 *
 * Deux contrôles vont au-delà de la simple lecture de l'enregistrement :
 * le repli sur le domaine organisationnel, sans quoi un sous-domaine sans
 * enregistrement propre serait déclaré non protégé à tort ; et l'autorisation
 * des destinataires externes de rapports (§7.1), le plus souvent oubliée — sans
 * elle, les rapports sont rejetés en silence.
 */
class DmarcAnalyzer
{
    private const VALID_POLICIES = ['none', 'quarantine', 'reject'];

    private static ?Rules $rules = null;

    public function __construct(private DnsResolver $dns)
    {
    }

    public function analyze(string $domain): array
    {
        $findings = [];
        $lookupDomain = $domain;

        $records = $this->dns->txtStartingWith('_dmarc.' . $domain, 'v=DMARC1');

        // Repli sur le domaine organisationnel, comme le font les fournisseurs.
        if ($records === []) {
            $organizational = $this->organizationalDomain($domain);

            if ($organizational && $organizational !== $domain) {
                $records = $this->dns->txtStartingWith('_dmarc.' . $organizational, 'v=DMARC1');

                if ($records !== []) {
                    $lookupDomain = $organizational;
                    $findings[] = Finding::info('dmarc_inherited', ['domain' => $organizational]);
                }
            }
        }

        if ($records === []) {
            return [
                'record' => null,
                'policy' => null,
                'findings' => [Finding::error('dmarc_missing', ['domain' => $domain])->toArray()],
            ];
        }

        if (count($records) > 1) {
            $findings[] = Finding::error('dmarc_multiple', ['count' => count($records)]);
        }

        $record = $records[0];
        $tags = $this->tags($record);

        // La RFC impose v=DMARC1 en toute première position.
        if (! isset($tags['v']) || array_key_first($tags) !== 'v') {
            $findings[] = Finding::error('dmarc_version_not_first');
        }

        $policy = strtolower($tags['p'] ?? '');

        if ($policy === '') {
            $findings[] = Finding::error('dmarc_no_policy');
        } elseif (! in_array($policy, self::VALID_POLICIES, true)) {
            $findings[] = Finding::error('dmarc_invalid_policy', ['policy' => $policy]);
        } elseif ($policy === 'none') {
            $findings[] = Finding::warning('dmarc_policy_none');
        }

        $subdomainPolicy = strtolower($tags['sp'] ?? '');
        if ($subdomainPolicy !== '' && ! in_array($subdomainPolicy, self::VALID_POLICIES, true)) {
            $findings[] = Finding::error('dmarc_invalid_sp', ['policy' => $subdomainPolicy]);
        }

        $pct = isset($tags['pct']) ? (int) $tags['pct'] : 100;
        if ($pct < 100) {
            $findings[] = Finding::warning('dmarc_partial_pct', ['pct' => $pct]);
        }

        foreach (['adkim' => 'dmarc_invalid_adkim', 'aspf' => 'dmarc_invalid_aspf'] as $tag => $code) {
            if (isset($tags[$tag]) && ! in_array(strtolower($tags[$tag]), ['r', 's'], true)) {
                $findings[] = Finding::error($code, ['value' => $tags[$tag]]);
            }
        }

        if (empty($tags['rua'])) {
            $findings[] = Finding::warning('dmarc_no_rua');
        }

        // Autorisation des destinataires externes, pour rua comme pour ruf
        foreach (['rua', 'ruf'] as $tag) {
            foreach ($this->reportDomains($tags[$tag] ?? '') as $reportDomain) {
                if ($this->sameOrganization($reportDomain, $lookupDomain)) {
                    continue;
                }

                if (! $this->externalReportingAuthorized($lookupDomain, $reportDomain)) {
                    $findings[] = Finding::error('dmarc_external_not_authorized', [
                        'report_domain' => $reportDomain,
                        'domain' => $lookupDomain,
                    ]);
                }
            }
        }

        return [
            'record' => $record,
            'policy' => $policy ?: null,
            'subdomain_policy' => $subdomainPolicy ?: null,
            'pct' => $pct,
            'alignment' => [
                'dkim' => strtolower($tags['adkim'] ?? 'r'),
                'spf' => strtolower($tags['aspf'] ?? 'r'),
            ],
            'enforced' => in_array($policy, ['quarantine', 'reject'], true) && $pct === 100,
            'findings' => array_map(fn (Finding $f) => $f->toArray(), $findings),
        ];
    }

    /**
     * RFC 7489 §7.1 : un domaine tiers doit publier son consentement à recevoir
     * les rapports, sous la forme d'un TXT dédié.
     */
    private function externalReportingAuthorized(string $dmarcDomain, string $reportDomain): bool
    {
        $host = $dmarcDomain . '._report._dmarc.' . $reportDomain;

        if ($this->dns->txtStartingWith($host, 'v=DMARC1') !== []) {
            return true;
        }

        // Autorisation générale, publiée avec un joker.
        return $this->dns->txtStartingWith('*._report._dmarc.' . $reportDomain, 'v=DMARC1') !== [];
    }

    /** @return array<int, string> */
    private function reportDomains(string $value): array
    {
        $domains = [];

        foreach (explode(',', $value) as $uri) {
            $uri = trim($uri);
            if (stripos($uri, 'mailto:') !== 0) {
                continue;
            }
            // Le suffixe « !10m » limite la taille du rapport ; on l'écarte.
            $address = explode('!', substr($uri, 7))[0];
            if (str_contains($address, '@')) {
                $domains[] = strtolower(trim(substr(strrchr($address, '@'), 1)));
            }
        }

        return array_values(array_unique($domains));
    }

    private function sameOrganization(string $a, string $b): bool
    {
        return $this->organizationalDomain($a) === $this->organizationalDomain($b);
    }

    /** Domaine enregistrable, déterminé par la Public Suffix List. */
    private function organizationalDomain(string $domain): ?string
    {
        $rules = $this->rules();

        if ($rules === null) {
            // Repli grossier, mais mieux que rien : les deux derniers libellés.
            $labels = explode('.', $domain);
            return count($labels) >= 2 ? implode('.', array_slice($labels, -2)) : $domain;
        }

        try {
            return $rules->resolve($domain)->registrableDomain()->toString() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function rules(): ?Rules
    {
        if (self::$rules !== null) {
            return self::$rules;
        }

        $path = storage_path('app/psl/public_suffix_list.dat');

        if (! is_readable($path)) {
            Log::warning('[DmarcAnalyzer] Public Suffix List absente', ['path' => $path]);
            return null;
        }

        try {
            return self::$rules = Rules::fromPath($path);
        } catch (\Throwable $e) {
            Log::warning('[DmarcAnalyzer] PSL illisible', ['error' => $e->getMessage()]);
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

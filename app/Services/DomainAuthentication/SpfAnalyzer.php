<?php

namespace App\Services\DomainAuthentication;

/**
 * Analyse l'enregistrement SPF d'un domaine — RFC 7208.
 *
 * Le contrôle décisif est la limite de dix requêtes DNS : au-delà, les
 * fournisseurs renvoient un PermError et cessent purement et simplement
 * d'évaluer le SPF. Un enregistrement parfaitement écrit peut donc ne servir à
 * rien, et c'est invisible sans compter les inclusions en cascade.
 */
class SpfAnalyzer
{
    /** RFC 7208 §4.6.4 */
    private const LOOKUP_LIMIT = 10;
    private const VOID_LOOKUP_LIMIT = 2;

    public function __construct(private DnsResolver $dns)
    {
    }

    public function analyze(string $domain): array
    {
        $this->dns->resetVoidLookups();

        $records = $this->dns->txtStartingWith($domain, 'v=spf1');
        $findings = [];

        if ($records === []) {
            return [
                'record' => null,
                'policy' => null,
                'lookups' => null,
                'lookup_limit' => self::LOOKUP_LIMIT,
                'findings' => [Finding::error('spf_missing', ['domain' => $domain])->toArray()],
            ];
        }

        if (count($records) > 1) {
            // Plusieurs enregistrements : PermError, le SPF n'est pas évalué.
            $findings[] = Finding::error('spf_multiple', ['count' => count($records)]);
        }

        $record = $records[0];
        [$policy, $policySource] = $this->resolvePolicy($record, $domain);

        if ($policy === null) {
            $findings[] = Finding::warning('spf_no_all');
        } elseif ($policySource !== null) {
            // La politique vient d'un redirect : le dire évite de croire que
            // l'enregistrement n'en déclare aucune.
            $findings[] = Finding::info('spf_policy_from_redirect', ['domain' => $policySource]);
        }

        if ($policy !== null) {
            if (str_starts_with($policy, 'pass')) {
                $findings[] = Finding::error('spf_plus_all');
            } elseif (str_starts_with($policy, 'neutral')) {
                // « ?all » revient à ne rien déclarer : ce n'est pas une simple
                // observation, l'enregistrement ne protège pas le domaine.
                $findings[] = Finding::warning('spf_neutral_all');
            } elseif (str_starts_with($policy, 'soft')) {
                $findings[] = Finding::info('spf_soft_all');
            }
        }

        $lookups = $this->countLookups($record, $domain, [], 0, $findings);

        if ($lookups > self::LOOKUP_LIMIT) {
            $findings[] = Finding::error('spf_too_many_lookups', [
                'count' => $lookups,
                'limit' => self::LOOKUP_LIMIT,
            ]);
        } elseif ($lookups >= self::LOOKUP_LIMIT - 1) {
            // À une requête de la limite : toute évolution du SPF la franchira.
            $findings[] = Finding::warning('spf_lookups_near_limit', [
                'count' => $lookups,
                'limit' => self::LOOKUP_LIMIT,
            ]);
        }

        if ($this->dns->voidLookups() > self::VOID_LOOKUP_LIMIT) {
            $findings[] = Finding::error('spf_too_many_void_lookups', [
                'count' => $this->dns->voidLookups(),
                'limit' => self::VOID_LOOKUP_LIMIT,
            ]);
        }

        if (preg_match('/(^|\s)[+\-~?]?ptr(\s|:|$)/i', $record)) {
            $findings[] = Finding::warning('spf_ptr_deprecated');
        }

        // Le redirect n'est réellement ignoré que si l'enregistrement porte
        // lui-même un « all » : on interroge donc le premier niveau seul.
        if (str_contains($record, 'redirect=') && $this->policy($record) !== null) {
            $findings[] = Finding::warning('spf_redirect_ignored');
        }

        if (str_contains($record, '%{')) {
            $findings[] = Finding::info('spf_macros');
        }

        // Un redirect dont la cible est construite par macro ne peut pas être
        // suivi hors d'une remise réelle : la politique reste indéterminée.
        if ($policy === null && preg_match('/\bredirect=[^\s;]*%/i', $record)) {
            $findings[] = Finding::warning('spf_redirect_macro');
        }

        // Tout ce qui suit « all » n'est jamais évalué (RFC 7208 §5.1).
        if ($this->hasTermsAfterAll($record)) {
            $findings[] = Finding::warning('spf_terms_after_all');
        }

        return [
            'record' => $record,
            'policy' => $policy,
            'lookups' => $lookups,
            'lookup_limit' => self::LOOKUP_LIMIT,
            'findings' => array_map(fn (Finding $f) => $f->toArray(), $findings),
        ];
    }

    /**
     * Politique effective de l'enregistrement, en suivant les `redirect`.
     *
     * RFC 7208 §6.1 : le modificateur `redirect` n'est évalué qu'en l'absence
     * de mécanisme `all`, et le résultat du domaine visé — son propre `all`
     * compris — devient celui de l'enregistrement d'origine. Ne lire que le
     * premier niveau ferait conclure à tort qu'aucune politique n'est déclarée.
     *
     * @return array{0: ?string, 1: ?string} [politique, domaine d'où elle vient]
     */
    private function resolvePolicy(string $record, string $domain, int $depth = 0): array
    {
        $policy = $this->policy($record);

        // Un « all » présent fait autorité et rend le redirect inopérant.
        if ($policy !== null) {
            return [$policy, $depth === 0 ? null : $domain];
        }

        if ($depth >= self::LOOKUP_LIMIT) {
            return [null, null];
        }

        if (! preg_match('/\bredirect=([^\s;]+)/i', $record, $m)) {
            return [null, null];
        }

        $target = trim($m[1]);

        // Une macro dépend du message : elle ne peut pas être dépliée ici.
        if ($target === '' || str_contains($target, '%')) {
            return [null, null];
        }

        $nested = $this->dns->txtStartingWith($target, 'v=spf1');

        return $nested === []
            ? [null, null]
            : $this->resolvePolicy($nested[0], $target, $depth + 1);
    }

    /**
     * Des mécanismes suivent-ils le « all » ?
     *
     * L'évaluation s'arrête au premier mécanisme qui correspond, et « all »
     * correspond toujours : ce qui vient après est donc mort. Les modificateurs
     * (exp, redirect) font exception, leur position étant libre.
     */
    private function hasTermsAfterAll(string $record): bool
    {
        $terms = preg_split('/\s+/', trim($record));
        $seenAll = false;

        foreach ($terms as $term) {
            if ($term === '' || stripos($term, 'v=spf1') === 0) {
                continue;
            }

            if ($seenAll && ! str_contains($term, '=')) {
                return true;
            }

            if (preg_match('/^[+\-~?]?all$/i', $term)) {
                $seenAll = true;
            }
        }

        return false;
    }

    /** Qualificateur du mécanisme « all », qui porte la politique. */
    private function policy(string $record): ?string
    {
        return match (true) {
            (bool) preg_match('/-all\b/i', $record) => 'fail (-all)',
            (bool) preg_match('/~all\b/i', $record) => 'soft fail (~all)',
            (bool) preg_match('/\?all\b/i', $record) => 'neutral (?all)',
            (bool) preg_match('/\+all\b/i', $record) => 'pass (+all)',
            (bool) preg_match('/(^|\s)all\b/i', $record) => 'pass (+all)',
            default => null,
        };
    }

    /**
     * Requêtes DNS qu'exige l'évaluation d'un enregistrement.
     *
     * include, a, mx, ptr, exists et redirect comptent chacun pour une requête,
     * et les include sont résolus en cascade : leurs propres mécanismes
     * s'ajoutent au même total.
     *
     * @param array<int, Finding> $findings
     */
    private function countLookups(
        string $record,
        string $domain,
        array $seen,
        int $depth,
        array &$findings
    ): int {
        // Garde-fou : inclusion circulaire ou cascade démesurée.
        if ($depth > self::LOOKUP_LIMIT || in_array($domain, $seen, true)) {
            if (in_array($domain, $seen, true)) {
                $findings[] = Finding::error('spf_circular_include', ['domain' => $domain]);
            }
            return 0;
        }

        $seen[] = $domain;
        $count = 0;

        foreach (preg_split('/\s+/', trim($record)) as $term) {
            if ($term === '' || stripos($term, 'v=spf1') === 0) {
                continue;
            }

            $bare = ltrim($term, '+-~?');

            // Mécanismes qui interrogent le DNS sans récursion
            if (preg_match('/^(a|mx|ptr|exists)([:\/]|$)/i', $bare)) {
                $count++;
                continue;
            }

            if (! preg_match('/^(include:|redirect=)(.+)$/i', $bare, $m)) {
                continue;
            }

            $count++;
            $target = trim($m[2]);

            // Les macros dépendent du message : on ne peut pas les déplier ici.
            if (str_contains($target, '%')) {
                continue;
            }

            $nested = $this->dns->txtStartingWith($target, 'v=spf1');

            if ($nested === []) {
                $findings[] = Finding::error('spf_include_unresolved', ['domain' => $target]);
                continue;
            }

            $count += $this->countLookups($nested[0], $target, $seen, $depth + 1, $findings);
        }

        return $count;
    }
}

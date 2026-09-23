<?php

namespace App\Services;

use App\Models\Test;
use Illuminate\Support\Collection;

/**
 * Calcule le score de conformité, la note et les recommandations d'un test
 * à partir des résultats d'authentification déjà collectés (SPF/DKIM/DMARC).
 */
class ComplianceScoreService
{
    /**
     * Répartition du score final.
     *
     * Le placement pèse plus lourd que l'authentification : c'est le fait
     * observé, ce que le visiteur est venu mesurer. L'authentification garde
     * un poids important car elle explique le placement et dit quoi corriger.
     */
    private const PLACEMENT_WEIGHT = 0.60;
    private const AUTH_WEIGHT = 0.40;

    /** Pondération des contrôles d'authentification, sur 100. */
    private const WEIGHTS = [
        'spf' => 35,
        'dkim' => 35,
        'dmarc' => 30,
    ];

    /**
     * Contrôles d'hygiène : ils nuancent la note sans pouvoir la faire basculer.
     */
    private const BONUS = [
        'reverse_dns' => 1.5,
        'bimi' => 1.5,
    ];

    /**
     * Crédit accordé à chaque issue, sur 100.
     *
     * Le spam conserve un crédit : le message est arrivé et reste récupérable,
     * ce qui n'est pas le cas d'un message jamais délivré.
     */
    private const PLACEMENT_CREDIT = [
        'inbox' => 100,
        'promotions' => 70,
        'updates' => 70,
        'forums' => 70,
        'other' => 20,
        'spam' => 20,
    ];

    /** Un DMARC en p=none déclare ne rien appliquer : crédit plafonné. */
    private const DMARC_NONE_FACTOR = 0.70;

    /** Un SPF non strictement "pass" conserve un crédit partiel. */
    private const SPF_PARTIAL = ['softfail', 'neutral'];

    /**
     * @return array{score:int, grade:string, checks:array, recommendations:array, placement:array}
     */
    public function analyze(Test $test): array
    {
        $results = $test->results ?? collect();

        $checks = [
            'spf' => $this->evaluateSpf($results),
            'dkim' => $this->evaluateEnum($results, 'dkim_result'),
            'dmarc' => $this->evaluateEnum($results, 'dmarc_result'),
            'reverse_dns' => $this->evaluateBoolean($results, 'reverse_dns_valid'),
            'bimi' => $this->evaluateBoolean($results, 'bimi_present'),
        ];

        // Sans aucun résultat exploitable, on ne présente pas un score trompeur.
        if ($results->isEmpty()) {
            return [
                'score' => null,
                'grade' => null,
                'checks' => $checks,
                'recommendations' => [],
                'placement' => $this->placement($results, $test),
                'providers' => $this->byProvider($test),
            ];
        }

        $auth = $this->authenticationScore($checks, $test);
        $placementScore = $this->placementScore($test);

        // Sans aucune des deux composantes, aucun score n'a de sens.
        if ($auth === null && $placementScore === null) {
            return [
                'score' => null,
                'grade' => null,
                'checks' => $checks,
                'recommendations' => [],
                'placement' => $this->placement($results, $test),
                'providers' => $this->byProvider($test),
            ];
        }

        // Si une composante manque, l'autre porte seule la note plutôt que
        // d'être diluée par un zéro qui n'a pas été mesuré.
        if ($auth === null) {
            $score = $placementScore;
        } elseif ($placementScore === null) {
            $score = $auth;
        } else {
            $score = self::PLACEMENT_WEIGHT * $placementScore + self::AUTH_WEIGHT * $auth;
        }

        $score = (int) max(0, min(100, round($score)));

        return [
            'score' => $score,
            'grade' => $this->grade($score),
            'auth_score' => $auth === null ? null : (int) round($auth),
            'placement_score' => $placementScore === null ? null : (int) round($placementScore),
            // Les composantes portent une note, comme le score global : deux
            // lettres se comparent d'un coup d'œil, deux nombres non.
            'auth_grade' => $auth === null ? null : $this->grade((int) round($auth)),
            'placement_grade' => $placementScore === null ? null : $this->grade((int) round($placementScore)),
            'checks' => $checks,
            'recommendations' => $this->pruneContradicted($this->recommendations($checks), $test),
            'placement' => $this->placement($results, $test),
            'providers' => $this->byProvider($test),
        ];
    }

    /**
     * Composante authentification, sur 100.
     *
     * Un contrôle sans donnée mesurée est exclu du calcul, dénominateur
     * compris : on ne pénalise pas ce qui n'a pas pu être vérifié.
     */
    private function authenticationScore(array $checks, Test $test): ?float
    {
        $earned = 0.0;
        $available = 0.0;

        foreach (self::WEIGHTS as $key => $weight) {
            if (($checks[$key]['total'] ?? 0) === 0) {
                continue;
            }

            $ratio = $checks[$key]['ratio'];

            // Un DMARC qui passe mais publie p=none ne protège rien : il ne
            // peut pas valoir autant qu'une politique réellement appliquée.
            if ($key === 'dmarc' && $this->dmarcPolicyIsNone($test)) {
                $ratio *= self::DMARC_NONE_FACTOR;
            }

            $available += $weight;
            $earned += $weight * $ratio;
        }

        if ($available <= 0) {
            return null;
        }

        $score = $earned / $available * 100;

        // Hygiène : un léger bonus, incapable de renverser la note.
        foreach (self::BONUS as $key => $points) {
            if (($checks[$key]['total'] ?? 0) > 0) {
                $score += $points * $checks[$key]['ratio'];
            } elseif ($key === 'bimi' && $this->bimiIsPublished($test)) {
                // Le DNS fait foi quand l'en-tête ne dit rien.
                $score += $points;
            }
        }

        return min(100, $score);
    }

    /**
     * Composante placement, sur 100.
     *
     * Moyennée par fournisseur et non par adresse : sinon un fournisseur
     * disposant de plusieurs boîtes pèserait mécaniquement plus lourd.
     */
    private function placementScore(Test $test): ?float
    {
        $finished = in_array($test->status, ['completed', 'timeout', 'cancelled'], true)
            || $test->isTimedOut();

        $scores = [];

        foreach ($test->emailAccounts->groupBy(fn ($a) => $a->getRealProvider()) as $accounts) {
            $credits = [];

            foreach ($accounts as $account) {
                $result = $test->results->firstWhere('email_account_id', $account->id);

                if ($result) {
                    $credits[] = self::PLACEMENT_CREDIT[$result->placement] ?? 20;
                    continue;
                }

                // Tant que le test tourne, une boîte muette n'est pas un échec.
                if ($finished) {
                    $credits[] = 0;
                }
            }

            if ($credits !== []) {
                $scores[] = array_sum($credits) / count($credits);
            }
        }

        return $scores === [] ? null : array_sum($scores) / count($scores);
    }

    /** La politique DMARC publiée vaut-elle p=none ? */
    private function dmarcPolicyIsNone(Test $test): bool
    {
        return ($test->domain_analysis['dmarc']['policy'] ?? null) === 'none';
    }

    private function bimiIsPublished(Test $test): bool
    {
        return ! empty($test->domain_analysis['bimi']['configured']);
    }

    /**
     * Écarte les recommandations que le diagnostic DNS dément.
     *
     * Les verdicts lus dans les en-têtes sont incomplets — `bimi_present` n'est
     * par exemple jamais renseigné par le chemin de traitement courant. Quand le
     * DNS montre un mécanisme correctement publié, il fait foi : recommander de
     * le mettre en place serait faux et décrédibiliserait le reste.
     */
    private function pruneContradicted(array $recommendations, Test $test): array
    {
        $dns = $test->domain_analysis ?? [];

        if ($dns === []) {
            return $recommendations;
        }

        return array_values(array_filter($recommendations, function (array $reco) use ($dns) {
            $mechanism = $dns[$reco['key']] ?? null;

            if (! $mechanism) {
                return true;
            }

            // BIMI est déclaré publié par son propre indicateur.
            $published = $reco['key'] === 'bimi'
                ? ! empty($mechanism['configured'])
                : ! empty($mechanism['record']);

            if (! $published) {
                return true;
            }

            // Publié mais fautif : la recommandation garde tout son sens.
            $hasError = collect($mechanism['findings'] ?? [])
                ->contains(fn ($f) => ($f['level'] ?? '') === 'error');

            return $hasError;
        }));
    }

    /** SPF : "pass" vaut plein crédit, softfail/neutral la moitié. */
    private function evaluateSpf(Collection $results): array
    {
        $values = $results->pluck('spf_result')->filter()->values();
        if ($values->isEmpty()) {
            return ['status' => 'none', 'ratio' => 0.0, 'passed' => 0, 'total' => 0];
        }

        $passed = $values->filter(fn ($v) => $v === 'pass')->count();
        $partial = $values->filter(fn ($v) => in_array($v, self::SPF_PARTIAL, true))->count();
        $ratio = ($passed + $partial * 0.5) / $values->count();

        return [
            'status' => $passed === $values->count() ? 'pass' : ($passed > 0 || $partial > 0 ? 'partial' : 'fail'),
            'ratio' => $ratio,
            'passed' => $passed,
            'total' => $values->count(),
        ];
    }

    private function evaluateEnum(Collection $results, string $column): array
    {
        $values = $results->pluck($column)->filter()->values();
        if ($values->isEmpty()) {
            return ['status' => 'none', 'ratio' => 0.0, 'passed' => 0, 'total' => 0];
        }

        $passed = $values->filter(fn ($v) => $v === 'pass')->count();

        return [
            'status' => $passed === $values->count() ? 'pass' : ($passed > 0 ? 'partial' : 'fail'),
            'ratio' => $passed / $values->count(),
            'passed' => $passed,
            'total' => $values->count(),
        ];
    }

    private function evaluateBoolean(Collection $results, string $column): array
    {
        $values = $results->pluck($column)->filter(fn ($v) => $v !== null)->values();
        if ($values->isEmpty()) {
            return ['status' => 'none', 'ratio' => 0.0, 'passed' => 0, 'total' => 0];
        }

        $passed = $values->filter(fn ($v) => (bool) $v)->count();

        return [
            'status' => $passed === $values->count() ? 'pass' : ($passed > 0 ? 'partial' : 'fail'),
            'ratio' => $passed / $values->count(),
            'passed' => $passed,
            'total' => $values->count(),
        ];
    }

    private function grade(int $score): string
    {
        // Seuils abaissés : le placement entrant dans la note, un A doit
        // désormais signifier « bien configuré ET bien délivré ».
        return match (true) {
            $score >= 90 => 'A',
            $score >= 75 => 'B',
            $score >= 60 => 'C',
            $score >= 45 => 'D',
            default => 'F',
        };
    }

    /**
     * Placement agrégé par fournisseur. On ne montre pas le détail adresse par
     * adresse : ce qui intéresse l'expéditeur, c'est le comportement de Gmail
     * ou de Microsoft, pas celui d'une boîte en particulier.
     *
     * Chaque ligne porte son audience (b2c / b2b, d'après le type saisi sur
     * les boîtes) : le filtrage grand public et professionnel diffère, les
     * résultats sont donc présentés en deux groupes.
     *
     * @return array<int, array{provider:string, audience:string, total:int, received:int, inbox:int, spam:int, other:int, rate:int, placement:string}>
     */
    public function byProvider(Test $test): array
    {
        return $test->emailAccounts
            ->groupBy(fn ($account) => self::audienceOf($account) . '|' . $account->getRealProvider())
            ->map(function ($accounts, $key) use ($test) {
                [$audience, $provider] = explode('|', $key, 2);
                $ids = $accounts->pluck('id');
                $rows = $test->results->whereIn('email_account_id', $ids);

                $inbox = $rows->where('placement', 'inbox')->count();
                $spam = $rows->where('placement', 'spam')->count();
                $other = $rows->count() - $inbox - $spam;
                $total = $accounts->count();

                // Le taux se rapporte à toutes les boîtes du fournisseur : une
                // adresse restée muette compte comme un échec de placement.
                $rate = $total > 0 ? (int) round($inbox / $total * 100) : 0;

                return [
                    'provider' => $provider,
                    'audience' => $audience,
                    'total' => $total,
                    'received' => $rows->count(),
                    'inbox' => $inbox,
                    'spam' => $spam,
                    'other' => $other,
                    'rate' => $rate,
                    'placement' => match (true) {
                        $rows->isEmpty() => 'missing',
                        $inbox >= max($spam, $other) => 'inbox',
                        $spam >= $other => 'spam',
                        default => 'other',
                    },
                ];
            })
            ->sortByDesc('rate')
            ->values()
            ->all();
    }

    /** Audience d'une boîte ; à défaut de type saisi, grand public. */
    public static function audienceOf($account): string
    {
        return $account->account_type === 'b2b' ? 'b2b' : 'b2c';
    }

    /** Répartition du placement, et décompte des boîtes ayant reçu. */
    private function placement(Collection $results, Test $test): array
    {
        $byPlacement = $results->groupBy('placement')->map->count();
        $total = $test->emailAccounts->count();
        $inbox = (int) ($byPlacement['inbox'] ?? 0);

        return [
            'inbox' => $inbox,
            'spam' => (int) ($byPlacement['spam'] ?? 0),
            'promotions' => (int) ($byPlacement['promotions'] ?? 0),
            'updates' => (int) ($byPlacement['updates'] ?? 0),
            'other' => max(0, $results->count() - $inbox
                - (int) ($byPlacement['spam'] ?? 0)
                - (int) ($byPlacement['promotions'] ?? 0)
                - (int) ($byPlacement['updates'] ?? 0)),
            'received' => $results->count(),
            'total' => $total,
            'missing' => max(0, $total - $results->count()),
        ];
    }

    /**
     * Recommandations ordonnées par gravité. Les clés de traduction vivent
     * dans messages.recommendations.* afin de rester bilingues.
     */
    private function recommendations(array $checks): array
    {
        $catalog = [
            'dmarc' => 'critical',
            'dkim' => 'high',
            'spf' => 'high',
            'reverse_dns' => 'medium',
            'bimi' => 'info',
        ];

        $out = [];
        foreach ($catalog as $key => $priority) {
            // Rien à recommander si le contrôle passe, ou s'il n'a pas pu être mesuré.
            if ($checks[$key]['status'] === 'pass' || $checks[$key]['total'] === 0) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'priority' => $priority,
                'status' => $checks[$key]['status'],
                'title' => __("messages.recommendations.{$key}_title"),
                'description' => __("messages.recommendations.{$key}_desc"),
            ];
        }

        $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'info' => 3];
        usort($out, fn ($a, $b) => $order[$a['priority']] <=> $order[$b['priority']]);

        return $out;
    }
}

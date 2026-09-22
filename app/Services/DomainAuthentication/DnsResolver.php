<?php

namespace App\Services\DomainAuthentication;

use Illuminate\Support\Facades\Log;

/**
 * Lectures DNS pour les analyseurs d'authentification.
 *
 * Les réponses sont mémorisées le temps d'une analyse : un même domaine est
 * interrogé plusieurs fois lors de l'expansion récursive d'un SPF, et il serait
 * absurde de repartir sur le réseau à chaque fois.
 */
class DnsResolver
{
    /** @var array<string, array<int, string>> */
    private array $memo = [];

    /** Nombre de requêtes n'ayant renvoyé aucun enregistrement. */
    private int $voidLookups = 0;

    /**
     * Toutes les entrées TXT d'un hôte, fragments recollés.
     *
     * @return array<int, string>
     */
    public function txtAll(string $host): array
    {
        $host = rtrim(strtolower($host), '.');

        if (isset($this->memo[$host])) {
            return $this->memo[$host];
        }

        try {
            $records = @dns_get_record($host, DNS_TXT) ?: [];
        } catch (\Throwable $e) {
            Log::debug('[DnsResolver] TXT lookup failed', ['host' => $host, 'error' => $e->getMessage()]);
            $records = [];
        }

        $found = [];
        foreach ($records as $record) {
            // Une valeur de plus de 255 octets est découpée en fragments.
            $txt = isset($record['entries']) ? implode('', $record['entries']) : ($record['txt'] ?? '');
            if ($txt !== '') {
                $found[] = $txt;
            }
        }

        if ($found === []) {
            $this->voidLookups++;
        }

        return $this->memo[$host] = $found;
    }

    /**
     * Entrées TXT correspondant à un préfixe, par exemple « v=spf1 ».
     *
     * @return array<int, string>
     */
    public function txtStartingWith(string $host, string $prefix): array
    {
        return array_values(array_filter(
            $this->txtAll($host),
            fn (string $txt) => stripos($txt, $prefix) === 0
        ));
    }

    /** Un hôte répond-il quelque chose ? Sert aux mécanismes a / mx. */
    public function hostExists(string $host): bool
    {
        $exists = @checkdnsrr(rtrim($host, '.'), 'A')
            || @checkdnsrr(rtrim($host, '.'), 'AAAA')
            || @checkdnsrr(rtrim($host, '.'), 'MX');

        if (! $exists) {
            $this->voidLookups++;
        }

        return $exists;
    }

    public function voidLookups(): int
    {
        return $this->voidLookups;
    }

    public function resetVoidLookups(): void
    {
        $this->voidLookups = 0;
    }
}

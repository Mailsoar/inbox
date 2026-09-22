<?php

namespace App\Services\DomainAuthentication;

/**
 * Analyse les clés DKIM publiées par le domaine signataire — RFC 6376.
 *
 * Les sélecteurs viennent des en-têtes `DKIM-Signature` du message reçu : rien
 * dans le DNS ne permet de les énumérer, il faut donc partir de ce qui a
 * réellement servi à signer.
 */
class DkimAnalyzer
{
    private const MIN_KEY_BITS = 1024;
    private const RECOMMENDED_KEY_BITS = 2048;

    public function __construct(private DnsResolver $dns)
    {
    }

    /**
     * @param array<int, array{d:?string, s:?string, a:?string}> $signatures
     */
    public function analyze(string $fromDomain, array $signatures): array
    {
        if ($signatures === []) {
            return [
                'record' => null,
                'selector' => null,
                'findings' => [Finding::error('dkim_no_signature')->toArray()],
            ];
        }

        $findings = [];

        if (count($signatures) > 1) {
            $findings[] = Finding::info('dkim_multiple_signatures', ['count' => count($signatures)]);
        }

        // On rend compte de la première signature, tout en contrôlant les autres.
        $primary = $signatures[0];
        $domain = $primary['d'] ?: $fromDomain;
        $selector = $primary['s'];

        if (! $selector) {
            return [
                'record' => null,
                'selector' => null,
                'findings' => [Finding::error('dkim_no_selector')->toArray()],
            ];
        }

        // L'alignement DMARC exige que le domaine signataire couvre le From.
        if ($domain && ! $this->aligned($domain, $fromDomain)) {
            $findings[] = Finding::warning('dkim_misaligned', ['signing' => $domain, 'from' => $fromDomain]);
        }

        foreach ($signatures as $signature) {
            if ($signature['a'] && stripos($signature['a'], 'sha1') !== false) {
                $findings[] = Finding::warning('dkim_sha1_signature', ['algorithm' => $signature['a']]);
                break;
            }
        }

        $records = $this->dns->txtAll($selector . '._domainkey.' . $domain);
        $record = $this->firstKeyRecord($records);

        if ($record === null) {
            $findings[] = Finding::error('dkim_key_missing', ['selector' => $selector, 'domain' => $domain]);

            return [
                'record' => null,
                'selector' => $selector,
                'findings' => array_map(fn (Finding $f) => $f->toArray(), $findings),
            ];
        }

        $tags = $this->tags($record);

        // Une clé publique vide signale une clé révoquée (RFC 6376 §3.6.1).
        if (! isset($tags['p']) || trim($tags['p']) === '') {
            $findings[] = Finding::error('dkim_key_revoked', ['selector' => $selector]);
        }

        $flags = array_map('trim', explode(':', $tags['t'] ?? ''));

        if (in_array('y', $flags, true)) {
            $findings[] = Finding::warning('dkim_testing_mode');
        }

        if (isset($tags['h']) && stripos($tags['h'], 'sha1') !== false) {
            $findings[] = Finding::warning('dkim_sha1_allowed');
        }

        $algorithm = strtolower(trim($tags['k'] ?? 'rsa'));
        $bits = $algorithm === 'rsa' ? $this->keyBits($tags['p'] ?? '') : null;

        if ($algorithm === 'rsa' && $bits !== null) {
            if ($bits < self::MIN_KEY_BITS) {
                $findings[] = Finding::error('dkim_key_too_short', ['bits' => $bits, 'min' => self::MIN_KEY_BITS]);
            } elseif ($bits < self::RECOMMENDED_KEY_BITS) {
                $findings[] = Finding::warning('dkim_key_weak', ['bits' => $bits, 'recommended' => self::RECOMMENDED_KEY_BITS]);
            }
        }

        return [
            'record' => $record,
            'selector' => $selector,
            'signing_domain' => $domain,
            'algorithm' => $algorithm,
            'key_bits' => $bits,
            'findings' => array_map(fn (Finding $f) => $f->toArray(), $findings),
        ];
    }

    /** @param array<int, string> $records */
    private function firstKeyRecord(array $records): ?string
    {
        foreach ($records as $txt) {
            if (stripos($txt, 'v=DKIM1') === 0 || str_contains($txt, 'p=')) {
                return $txt;
            }
        }

        return null;
    }

    /**
     * Longueur du modulus RSA, en bits.
     *
     * La clé publique est encodée en base64 dans le tag `p=` ; on la replie en
     * PEM pour laisser OpenSSL en donner la taille exacte.
     */
    private function keyBits(string $publicKey): ?int
    {
        $publicKey = preg_replace('/\s+/', '', $publicKey);

        if ($publicKey === '') {
            return null;
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split($publicKey, 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $key = @openssl_pkey_get_public($pem);

        if ($key === false) {
            return null;
        }

        $details = @openssl_pkey_get_details($key);

        return $details['bits'] ?? null;
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

    /** Alignement relâché : le domaine signataire couvre-t-il celui du From ? */
    private function aligned(string $signing, string $from): bool
    {
        $signing = strtolower($signing);
        $from = strtolower($from);

        return $signing === $from
            || str_ends_with($from, '.' . $signing)
            || str_ends_with($signing, '.' . $from);
    }
}

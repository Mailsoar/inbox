<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\Test;
use Illuminate\Support\Str;

/**
 * Prépare ce que la page publique affiche avant qu'un test n'existe :
 * la liste des boîtes de test et un identifiant de suivi réservé.
 *
 * L'identifiant est mémorisé en session afin que le test créé porte bien
 * celui que le visiteur a copié dans son email.
 */
class PublicTestPreview
{
    private const SESSION_KEY = 'reserved_test_id';

    /** Identifiant de suivi réservé pour la session courante. */
    public function trackingId(): string
    {
        $reserved = session(self::SESSION_KEY);

        // On réutilise l'identifiant tant qu'il reste libre.
        if ($reserved && preg_match('/^MS-[A-Z0-9]{6}$/', $reserved)
            && ! Test::where('unique_id', $reserved)->exists()) {
            return $reserved;
        }

        do {
            $id = 'MS-' . strtoupper(Str::random(6));
        } while (Test::where('unique_id', $id)->exists());

        session([self::SESSION_KEY => $id]);

        return $id;
    }

    /** Oublie l'identifiant réservé, une fois le test créé. */
    public function release(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Boîtes de test présentées au visiteur. Elles sont fixes : ce sont les
     * comptes actifs, ceux-là mêmes qui seront rattachés au test.
     *
     * @return array<int, array{email:string, provider:string}>
     */
    public function seedList(): array
    {
        return EmailAccount::where('is_active', true)
            ->orderBy('provider')
            ->get()
            ->map(fn (EmailAccount $account) => [
                'email' => $account->email,
                'provider' => $account->getRealProvider(),
            ])
            ->values()
            ->all();
    }
}

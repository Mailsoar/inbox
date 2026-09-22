@php
    /**
     * Pictogramme d'un fournisseur, repris de la maquette.
     *
     * Ce sont des caractères Unicode et non des logos : l'enveloppe sert à la
     * fois à Gmail et de repli, conformément à la maquette.
     */
    $size = $size ?? 'w-10 h-10';

    $slug = \Illuminate\Support\Str::of($provider)
        ->lower()
        ->replaceMatches('/[^a-z0-9]+/', '_')
        ->trim('_')
        ->value();

    // Les libellés composés sont ramenés à la marque de la maquette
    $family = match (true) {
        str_contains($slug, 'gmail'), str_contains($slug, 'google') => 'gmail',
        str_contains($slug, 'outlook'), str_contains($slug, 'microsoft'), str_contains($slug, 'hotmail') => 'outlook',
        str_contains($slug, 'yahoo') => 'yahoo',
        str_contains($slug, 'apple'), str_contains($slug, 'icloud') => 'apple',
        default => null,
    };

    $glyph = [
        'gmail' => '✉',
        'outlook' => '⊞',
        'yahoo' => '☉',
        // La maquette utilise ici le glyphe Apple U+F8FF, qui ne s'affiche que
        // sur les appareils Apple : ailleurs il laisse un carré vide.
        'apple' => '☁',
    ][$family] ?? '✉';
@endphp

<div class="{{ $size }} rounded-lg bg-muted flex items-center justify-center shrink-0 text-lg font-bold"
     title="{{ $provider }}" aria-hidden="true">
    {{ $glyph }}
</div>

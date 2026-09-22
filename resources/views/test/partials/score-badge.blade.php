@php
    /**
     * Note d'une composante, dans le même langage visuel que la jauge
     * principale : un anneau dont le remplissage suit le score, la lettre au
     * centre, le libellé dessous. Une lettre seule ne se comprend pas.
     */
    $radius = 34;
    $circumference = 2 * M_PI * $radius;
    $offset = $circumference - ($score / 100) * $circumference;
@endphp

<div class="flex items-center gap-3">
    <div class="relative w-20 h-20 shrink-0">
        <svg class="w-full h-full -rotate-90" viewBox="0 0 80 80" aria-hidden="true">
            <circle cx="40" cy="40" r="{{ $radius }}" stroke="hsl(var(--muted))" stroke-width="6" fill="none"/>
            <circle cx="40" cy="40" r="{{ $radius }}"
                    stroke="{{ $color }}" stroke-width="6" fill="none" stroke-linecap="round"
                    stroke-dasharray="{{ $circumference }}"
                    stroke-dashoffset="{{ $offset }}"/>
        </svg>
        <div class="absolute inset-0 flex items-center justify-center">
            <span class="text-2xl font-bold font-heading leading-none" style="color: {{ $color }}">{{ $grade }}</span>
        </div>
    </div>
    <div class="min-w-0">
        <div class="text-sm font-medium leading-tight">{{ $label }}</div>
        <div class="text-xs text-muted-foreground tabular-nums">{{ $score }}/100</div>
    </div>
</div>

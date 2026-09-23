@php
    $radius = 80;
    $circumference = 2 * M_PI * $radius;
    // Sans score (aucun email reçu), anneau vide et tiret à la place du chiffre
    $offset = $circumference - (($score ?? 0) / 100) * $circumference;
@endphp

<div class="relative w-48 h-48 mx-auto">
    <svg class="w-full h-full -rotate-90" viewBox="0 0 200 200" aria-hidden="true">
        <circle cx="100" cy="100" r="{{ $radius }}" stroke="hsl(var(--muted))" stroke-width="12" fill="none"/>
        <circle cx="100" cy="100" r="{{ $radius }}"
                stroke="{{ $color }}" stroke-width="12" fill="none" stroke-linecap="round"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $circumference }}"
                style="animation: score-ring 1s ease-out forwards; --score-offset: {{ $offset }}"/>
    </svg>
    <div class="absolute inset-0 flex flex-col items-center justify-center">
        <div class="text-4xl font-bold tabular-nums font-heading {{ $score === null ? 'text-muted-foreground' : '' }}">{{ $score ?? '—' }}</div>
        <div class="text-xs text-muted-foreground uppercase tracking-wide">{{ __('messages.results.deliverability_score') }}</div>
        @if ($grade)
            <div class="text-2xl font-bold mt-1" style="color: {{ $color }}">{{ $grade }}</div>
        @else
            <div class="text-xs text-muted-foreground mt-2">{{ __('messages.results.data_unavailable') }}</div>
        @endif
    </div>
</div>

@once
@push('head')
<style>
    @keyframes score-ring {
        to { stroke-dashoffset: var(--score-offset); }
    }
</style>
@endpush
@endonce

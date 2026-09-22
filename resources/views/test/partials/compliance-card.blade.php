@php
    $optional = $optional ?? false;
    $dns = $dns ?? null;
    $dnsKey = $dnsKey ?? null;

    // Un contrôle facultatif absent n'est pas un échec : on le signale en neutre.
    [$badgeTone, $iconTone, $badgeLabel] = match (true) {
        $check['status'] === 'pass' => ['text-emerald-600 bg-emerald-50', 'bg-emerald-50 text-emerald-600', __('messages.results.passed')],
        $check['status'] === 'partial' => ['text-amber-600 bg-amber-50', 'bg-amber-50 text-amber-600', __('messages.results.partial')],
        $optional => ['text-muted-foreground bg-muted', 'bg-muted text-muted-foreground', __('messages.results.absent')],
        default => ['text-red-600 bg-red-50', 'bg-red-50 text-red-600', __('messages.results.failed')],
    };

    // Phrase de constat : ce que le DNS a révélé, à défaut le taux mesuré.
    $message = match (true) {
        $dnsKey === 'spf' && ! empty($dns['record']) => __('messages.results.spf_found'),
        $dnsKey === 'dkim' && ! empty($dns['selector']) => __('messages.results.dkim_found', ['selector' => $dns['selector']]),
        $dnsKey === 'dmarc' && ! empty($dns['policy']) => __('messages.results.dmarc_found', ['policy' => $dns['policy']]),
        $dnsKey === 'bimi' && ! empty($dns['record']) => __('messages.results.bimi_found'),
        default => __('messages.results.check_summary', ['passed' => $check['passed'], 'total' => $check['total']]),
    };

    // Détail complémentaire propre à chaque mécanisme
    $detail = match ($dnsKey) {
        'spf' => ! empty($dns['policy']) || isset($dns['lookups'])
            ? [
                __('messages.results.policy'),
                trim(($dns['policy'] ?? '') . (isset($dns['lookups'])
                    ? ' · ' . $dns['lookups'] . '/' . ($dns['lookup_limit'] ?? 10) . ' ' . __('messages.results.spf_lookups')
                    : ''), ' ·'),
            ]
            : null,
        'dkim' => ! empty($dns['selector'])
            ? [__('messages.results.selector'), $dns['selector'] . (! empty($dns['key_bits']) ? ' · ' . $dns['key_bits'] . ' bits' : '')]
            : null,
        'dmarc' => ! empty($dns['policy'])
            ? [__('messages.results.policy'), $dns['policy'] . (isset($dns['pct']) && $dns['pct'] < 100 ? ' · pct=' . $dns['pct'] : '')]
            : null,
        default => null,
    };

    $findings = $dns['findings'] ?? [];
@endphp

<div class="border border-border rounded-xl p-5 bg-card">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $iconTone }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
                </svg>
            </div>
            <div class="min-w-0">
                <div class="font-semibold">{{ $label }}</div>
                <div class="text-xs text-muted-foreground">{{ $description }}</div>
            </div>
            @if (! empty($logoUrl))
                {{-- Aperçu du logo BIMI, tel qu'il s'afficherait en boîte --}}
                <img src="{{ $logoUrl }}" alt="" loading="lazy"
                     class="w-10 h-10 rounded-lg object-contain bg-background border border-border shrink-0 ml-1">
            @endif
        </div>
        <span class="inline-flex items-center gap-1 text-sm font-medium px-2.5 py-1 rounded-full shrink-0 {{ $badgeTone }}">
            @if ($check['status'] === 'pass')
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            @else
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            @endif
            {{ $badgeLabel }}
        </span>
    </div>

    <p class="text-sm text-muted-foreground mt-3">{{ $message }}</p>

    @if (! empty($dns['record']))
        <div class="mt-3 bg-muted/50 rounded-lg px-3 py-2 text-xs font-mono text-muted-foreground break-all max-h-20 overflow-hidden">
            {{ $dns['record'] }}
        </div>
    @endif

    @if ($detail)
        <div class="mt-2 text-xs text-muted-foreground">
            {{ $detail[0] }} : <span class="font-medium text-foreground">{{ $detail[1] }}</span>
        </div>
    @endif

    {{-- Anomalies relevées sur l'enregistrement --}}
    @if (! empty($findings))
        <ul class="mt-3 space-y-2">
            @foreach ($findings as $finding)
                @php($tone = match ($finding['level']) {
                    'error' => ['bg-red-50 text-red-700', __('messages.results.level_error')],
                    'warning' => ['bg-amber-50 text-amber-700', __('messages.results.level_warning')],
                    default => ['bg-blue-50 text-blue-700', __('messages.results.level_info')],
                })
                <li class="flex items-start gap-2 text-xs leading-relaxed">
                    {{-- L'étiquette dit explicitement de quoi il s'agit : une
                         pastille de couleur seule laissait le doute. --}}
                    <span class="shrink-0 rounded px-1.5 py-0.5 font-semibold uppercase text-[10px] tracking-wide {{ $tone[0] }}">
                        {{ $tone[1] }}
                    </span>
                    <span class="{{ $finding['level'] === 'info' ? 'text-muted-foreground' : 'text-foreground' }} pt-0.5">
                        {{ __('messages.findings.' . $finding['code'], $finding['params'] ?? []) }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Recommandation propre à ce mécanisme, au plus près du constat --}}
    @php($recommendation = $recommendation ?? null)
    @if ($recommendation)
        @php($recoTone = [
            'critical' => 'bg-red-50 text-red-700 border-red-200',
            'high' => 'bg-orange-50 text-orange-700 border-orange-200',
            'medium' => 'bg-amber-50 text-amber-700 border-amber-200',
            'info' => 'bg-blue-50 text-blue-700 border-blue-200',
        ][$recommendation['priority']] ?? 'bg-blue-50 text-blue-700 border-blue-200')

        <div class="mt-4 rounded-lg border p-3.5 {{ $recoTone }}">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <span class="font-semibold uppercase text-[10px] tracking-wide">{{ $recommendation['priority'] }}</span>
                <span class="font-semibold text-sm">{{ $recommendation['title'] }}</span>
            </div>
            <p class="text-xs leading-relaxed opacity-90">{{ $recommendation['description'] }}</p>
        </div>
    @endif
</div>

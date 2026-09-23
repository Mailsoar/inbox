@php
    $placement = $analysis['placement'];
    $checks = $analysis['checks'];
    $providers = $analysis['providers'];

    // Motif affiché sur les contrôles sans donnée
    $unavailableReason = $placement['received'] === 0
        ? __('messages.results.unavailable_no_email')
        : __('messages.results.unavailable_no_data');

    $gradeColor = [
        'A' => '#10b981', 'B' => '#f59e0b', 'C' => '#f97316', 'D' => '#f97316', 'F' => '#ef4444',
    ][$analysis['grade'] ?? ''] ?? 'hsl(var(--accent))';

    $priorityClass = [
        'critical' => 'bg-red-50 text-red-700 border-red-200',
        'high' => 'bg-orange-50 text-orange-700 border-orange-200',
        'medium' => 'bg-amber-50 text-amber-700 border-amber-200',
        'info' => 'bg-blue-50 text-blue-700 border-blue-200',
    ];

    // Icônes des trois contrôles d'authentification
    $authIcons = [
        'spf' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        'dkim' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
        'dmarc' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z',
        'bimi' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
    ];
@endphp

{{-- Toutes les sections restent visibles : sans donnée, elles le disent
     plutôt que de disparaître. --}}

{{-- Score de conformité et placement global --}}
<div class="card p-6 grid sm:grid-cols-[auto_1fr] gap-6 items-center">
    @include('test.partials.score-gauge', [
        'score' => $analysis['score'],
        'grade' => $analysis['grade'],
        'color' => $gradeColor,
    ])
    <div>
        <div class="text-sm font-medium text-muted-foreground mb-1">{{ __('messages.results.inbox_placement') }}</div>
        <div class="text-3xl font-bold font-heading">
            {{ $placement['inbox'] }}/{{ $placement['total'] }} {{ __('messages.results.inbox') }}
        </div>
        @php
            // Le constat doit désigner ce qui tire la note vers le bas :
            // dire « l'authentification a échoué » quand elle est parfaite
            // et que c'est le placement qui pèche décrédibilise le reste.
            $authScore = $analysis['auth_score'] ?? null;
            $placementScore = $analysis['placement_score'] ?? null;
            $good = 75;

            $verdictKey = match (true) {
                $analysis['score'] === null => 'verdict_no_data',
                $authScore === null || $placementScore === null => 'verdict_partial',
                $authScore >= $good && $placementScore >= $good => 'verdict_all_good',
                $authScore >= $good => 'verdict_placement_weak',
                $placementScore >= $good => 'verdict_auth_weak',
                default => 'verdict_both_weak',
            };
        @endphp

        <p class="text-sm text-muted-foreground mt-2">
            {{ __("messages.results.{$verdictKey}", [
                'inbox' => $placement['inbox'],
                'total' => $placement['total'],
            ]) }}
        </p>

        {{-- Les deux composantes du score, pour que la note soit lisible --}}
        @php
            // Teinte calculée ici : un ternaire imbriqué dans un @php(...)
            // en ligne casse l'analyseur de Blade.
            $components = [];
            foreach ([
                'placement_component' => ['placement_score', 'placement_grade'],
                'auth_component' => ['auth_score', 'auth_grade'],
            ] as $labelKey => [$scoreKey, $gradeKey]) {
                $value = $analysis[$scoreKey] ?? null;

                if ($value === null) {
                    continue;
                }

                $grade = $analysis[$gradeKey] ?? null;

                $components[] = [
                    'label' => __("messages.results.{$labelKey}"),
                    'grade' => $grade,
                    'score' => $value,
                    // Même palette que la jauge principale, pour que les
                    // trois notes se lisent sur la même échelle.
                    'color' => $gradeColor === null ? null : ([
                        'A' => '#10b981', 'B' => '#f59e0b', 'C' => '#f97316',
                        'D' => '#f97316', 'F' => '#ef4444',
                    ][$grade] ?? 'hsl(var(--accent))'),
                ];
            }
        @endphp

        @if ($components !== [])
            <div class="mt-4 grid grid-cols-2 gap-3">
                @foreach ($components as $component)
                    <div class="rounded-lg border border-border bg-background px-3 py-3">
                        @include('test.partials.score-badge', [
                            'score' => $component['score'],
                            'grade' => $component['grade'],
                            'color' => $component['color'],
                            'label' => $component['label'],
                        ])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- Parler à un expert --}}
<div class="border border-primary/20 rounded-2xl p-6 bg-primary/5 flex flex-col sm:flex-row items-center justify-between gap-4">
    <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
        <div>
            <h3 class="font-semibold text-lg">{{ __('messages.results.expert_title') }}</h3>
            <p class="text-sm text-muted-foreground mt-1 max-w-md">{{ __('messages.results.expert_desc') }}</p>
        </div>
    </div>
    {{-- Le href reste fonctionnel si le script Calendly ne charge pas --}}
    <a href="{{ config('mailsoar.expert_booking_url') }}" target="_blank" rel="noopener noreferrer"
       data-calendly class="btn-primary btn-lg gap-2 shrink-0">
        {{ __('messages.results.expert_cta') }}
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
        </svg>
    </a>
</div>

{{-- Conformité technique : SPF, DKIM, DMARC --}}
@php
    $mainChecks = collect(['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC']);

    // Chaque recommandation rejoint la carte du mécanisme qu'elle concerne :
    // elle y est bien plus lisible qu'isolée en bas de page.
    $recoByKey = collect($analysis['recommendations'] ?? [])->keyBy('key');
@endphp

@if ($mainChecks->isNotEmpty())
    <div>
        <h2 class="font-semibold text-lg mb-4">{{ __('messages.results.technical_compliance') }}</h2>

        {{-- Une carte par mécanisme, pleine largeur : plus de place pour le détail --}}
        <div class="space-y-4">
            @foreach ($mainChecks as $key => $label)
                @if ($analysisPending ?? false)
                    @include('test.partials.compliance-card-pending', [
                        'label' => $label,
                        'description' => __("messages.results.{$key}_full"),
                        'icon' => $authIcons[$key],
                    ])
                    @continue
                @endif

                @if (($checks[$key]['total'] ?? 0) === 0)
                    @include('test.partials.compliance-card-unavailable', [
                        'label' => $label,
                        'description' => __("messages.results.{$key}_full"),
                        'icon' => $authIcons[$key],
                        'reason' => $unavailableReason,
                    ])
                    @continue
                @endif

                @include('test.partials.compliance-card', [
                    'check' => $checks[$key],
                    'label' => $label,
                    'description' => __("messages.results.{$key}_full"),
                    'icon' => $authIcons[$key],
                    'dns' => $dns[$key] ?? null,
                    'dnsKey' => $key,
                    'recommendation' => $recoByKey[$key] ?? null,
                ])
            @endforeach
        </div>

        {{-- BIMI, facultatif, à la suite --}}
        @php($bimiDns = $dns['bimi'] ?? null)
        @if ($analysisPending ?? false)
            <div class="mt-4">
                @include('test.partials.compliance-card-pending', [
                    'label' => 'BIMI',
                    'description' => __('messages.results.bimi_full'),
                    'icon' => $authIcons['bimi'],
                ])
            </div>
        @elseif ($bimiDns)
            @php($bimiCheck = [
                'status' => ! empty($bimiDns['configured'])
                    ? (collect($bimiDns['findings'] ?? [])->contains(fn ($f) => $f['level'] === 'error') ? 'fail' : 'pass')
                    : 'none',
                'ratio' => ! empty($bimiDns['configured']) ? 1 : 0,
                'passed' => ! empty($bimiDns['configured']) ? 1 : 0,
                'total' => 1,
            ])
            <div class="mt-4">
                @include('test.partials.compliance-card', [
                    'check' => $bimiCheck,
                    'label' => 'BIMI',
                    'description' => __('messages.results.bimi_full'),
                    'icon' => $authIcons['bimi'],
                    'optional' => true,
                    'dns' => $bimiDns,
                    'dnsKey' => 'bimi',
                    'logoUrl' => empty($bimiDns['findings']) && ! empty($bimiDns['logo_url']) ? $bimiDns['logo_url'] : null,
                    'recommendation' => $recoByKey['bimi'] ?? null,
                ])
            </div>
        @else
            <div class="mt-4">
                @include('test.partials.compliance-card-unavailable', [
                    'label' => 'BIMI',
                    'description' => __('messages.results.bimi_full'),
                    'icon' => $authIcons['bimi'],
                    'reason' => $unavailableReason,
                ])
            </div>
        @endif
    </div>
@endif

{{-- Placement par fournisseur, en deux groupes : le filtrage grand public
     et professionnel diffère, un taux global masquerait l'écart. --}}
@php($audiences = collect($providers)->groupBy('audience'))
<div>
    <h2 class="font-semibold text-lg mb-4">{{ __('messages.results.results_by_provider') }}</h2>
    <div class="space-y-8">
        @foreach (['b2c', 'b2b'] as $audience)
            @continue(empty($audiences[$audience]))
            {{-- Directives courtes uniquement : un bloc php long, ici, avalerait les directives courtes qui précèdent --}}
            @php($group = $audiences[$audience])
            @php($groupInbox = $group->sum('inbox'))
            @php($groupTotal = $group->sum('total'))
            @php($groupRate = $groupTotal ? (int) round($groupInbox / $groupTotal * 100) : 0)
            <section>
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ __('messages.results.audience_' . $audience) }}
                    </h3>
                    <span class="text-sm text-muted-foreground">
                        <span class="font-semibold text-foreground tabular-nums">{{ $groupInbox }}/{{ $groupTotal }}</span>
                        {{ __('messages.results.in_inbox') }} · <span class="tabular-nums">{{ $groupRate }}%</span>
                    </span>
                </div>
                <div class="space-y-3">
                    @foreach ($group as $row)
                        @php($tone = match ($row['placement']) {
                            'inbox' => ['text-emerald-600', 'bg-emerald-50', 'bg-emerald-500', __('messages.results.inbox')],
                            'spam' => ['text-amber-600', 'bg-amber-50', 'bg-amber-500', __('messages.results.spam')],
                            'other' => ['text-blue-600', 'bg-blue-50', 'bg-blue-500', __('messages.results.promotions')],
                            default => ['text-muted-foreground', 'bg-muted', 'bg-muted-foreground/40', __('messages.results.not_received')],
                        })
                        <div class="flex items-center gap-4 border border-border rounded-xl p-4 bg-card">
                            @include('test.partials.provider-icon', ['provider' => $row['provider']])
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="font-medium truncate">{{ $row['provider'] }}</span>
                                        @include('test.partials.provider-flag', ['provider' => $row['provider']])
                                    </div>
                                    <span class="inline-flex items-center gap-1 text-sm font-medium shrink-0 {{ $tone[0] }} {{ $tone[1] }} px-2.5 py-0.5 rounded-full">
                                        {{ $tone[3] }}
                                    </span>
                                </div>
                                <div class="mt-2 flex items-center gap-3">
                                    <div class="flex-1 h-2 rounded-full bg-muted overflow-hidden">
                                        <div class="h-full rounded-full {{ $tone[2] }}" style="width: {{ $row['rate'] }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium tabular-nums w-12 text-right">{{ $row['rate'] }}%</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
        @if (empty($providers))
            <p class="text-sm text-muted-foreground">{{ __('messages.results.not_received') }}</p>
        @endif
    </div>
</div>

{{-- Recommandations sans carte associée (ex. reverse DNS) --}}
@php($orphanRecos = collect($analysis['recommendations'] ?? [])
    ->reject(fn ($r) => in_array($r['key'], ['spf', 'dkim', 'dmarc', 'bimi'], true)))

@if ($orphanRecos->isNotEmpty())
    <div>
        <h2 class="font-semibold text-lg mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
            {{ __('messages.results.recommendations') }}
        </h2>
        <div class="space-y-2">
            @foreach ($orphanRecos as $reco)
                <div class="border rounded-xl p-4 text-sm {{ $priorityClass[$reco['priority']] ?? $priorityClass['info'] }}">
                    <span class="font-semibold uppercase text-xs mr-2">{{ $reco['priority'] }}</span>
                    <span class="font-semibold">{{ $reco['title'] }}</span>
                    <p class="mt-1 leading-relaxed opacity-90">{{ $reco['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
@endif

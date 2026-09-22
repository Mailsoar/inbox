@extends('layouts.public')

@section('title', ($hasResults ? __('messages.results.title') : __('messages.results.test_progress')) . ' — ' . $test->unique_id)

@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10 sm:py-16">

    @if (! $hasResults)
        {{-- ══════════ ATTENTE ══════════ --}}
        <div class="flex justify-center mb-8">
            @include('partials.logo', ['class' => 'h-10 w-auto'])
        </div>

        <div class="flex flex-col items-center w-full">

            {{-- Anneau de décompte --}}
            <div class="relative w-64 h-64">
                <svg class="w-full h-full -rotate-90" viewBox="0 0 260 260" aria-hidden="true">
                    <circle cx="130" cy="130" r="110" stroke="hsl(var(--muted))" stroke-width="10" fill="none"/>
                    <circle data-ring cx="130" cy="130" r="110" stroke="hsl(var(--primary))" stroke-width="10"
                            fill="none" stroke-linecap="round"
                            stroke-dasharray="691.15" stroke-dashoffset="691.15"
                            style="transition: stroke-dashoffset .5s linear"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <div data-countdown class="text-5xl font-bold tabular-nums font-heading">--:--</div>
                    <div data-ring-label class="text-sm text-muted-foreground mt-2">{{ __('messages.test.ring_waiting') }}</div>
                </div>
            </div>

            <p data-status class="mt-10 text-lg font-medium text-center max-w-md transition-opacity duration-300"></p>

            <div class="mt-3 text-sm text-muted-foreground">
                {{ __('messages.test.unique_id') }} :
                <span data-tracking-id class="font-semibold text-foreground font-mono">{{ $test->unique_id }}</span>
            </div>

            {{-- Rattrapage : récupérer à nouveau l'identifiant ou la liste d'adresses --}}
            <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                <button type="button" class="btn-outline btn-sm gap-2" data-copy="tracking">
                    <span data-copy-idle class="inline-flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                        </svg>
                        {{ __('messages.test.copy_id') }}
                    </span>
                    <span data-copy-done class="hidden items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ __('messages.test.copied') }}
                    </span>
                </button>

                <button type="button" class="btn-outline btn-sm gap-2" data-copy="seeds">
                    <span data-copy-idle class="inline-flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                        </svg>
                        {{ __('messages.test.copy_seed_list') }}
                    </span>
                    <span data-copy-done class="hidden items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ __('messages.test.copied_clipboard') }}
                    </span>
                </button>

                <button type="button" data-download-csv
                        class="text-xs text-muted-foreground hover:text-foreground underline underline-offset-2 inline-flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span data-dl-idle>{{ __('messages.test.download_csv_instead') }}</span>
                    <span data-dl-done class="hidden">{{ __('messages.test.downloaded') }}</span>
                </button>
            </div>

            {{-- Flux des arrivées --}}
            <div class="mt-8 w-full max-w-sm border border-border rounded-xl bg-card overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-border bg-muted/30">
                    <span class="text-sm font-medium flex items-center gap-2">
                        <span data-feed-dot class="w-2 h-2 rounded-full bg-muted-foreground"></span>
                        {{ __('messages.test.delivery_feed') }}
                    </span>
                    <span data-feed-count class="text-xs text-muted-foreground tabular-nums">
                        {{ $test->results->count() }}/{{ $test->emailAccounts->count() }} {{ __('messages.test.received_count') }}
                    </span>
                </div>
                <div data-feed class="px-4 py-3 space-y-2 min-h-[80px]"></div>
            </div>

            {{-- Rappel : la page peut être quittée puis rouverte --}}
            <p class="mt-6 text-xs text-muted-foreground text-center max-w-sm">
                {{ __('messages.test.bookmark_hint') }}
            </p>
        </div>
    @else
        {{-- ══════════ RÉSULTATS ══════════ --}}
        <div class="flex items-start justify-between flex-wrap gap-4 mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold font-heading">{{ __('messages.results.title') }}</h1>
                <p class="text-sm text-muted-foreground mt-2">
                    @if ($test->sending_domain)
                        {{ __('messages.results.sending_domain') }} :
                        <span class="font-semibold text-foreground">{{ $test->sending_domain }}</span>
                        <span class="mx-2 text-border">·</span>
                    @endif
                    {{ __('messages.test.unique_id') }} :
                    <span class="font-mono text-foreground">{{ $test->unique_id }}</span>
                </p>
            </div>
            {{-- Sobre en haut : l'action principale de cette page, ce sont les résultats --}}
            <a href="{{ route('home') }}" class="btn-outline btn-md gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ __('messages.home.create_test') }}
            </a>
        </div>

        @if (! $isFinished)
            {{-- Résultats partiels : d'autres réponses peuvent encore arriver --}}
            <div class="rounded-xl border border-border bg-card px-4 py-3 mb-6 flex items-center gap-3 text-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                <span class="flex-1">
                    {{ __('messages.test.partial_results', [
                        'received' => $test->results->count(),
                        'total' => $test->emailAccounts->count(),
                    ]) }}
                </span>
                <span data-refresh-note class="text-xs text-muted-foreground shrink-0"></span>
            </div>
        @endif

        <div class="space-y-6">
            @include('test.partials.results-body', ['test' => $test, 'analysis' => $analysis])
        </div>

        {{-- Relancer un test : l'action qui suit naturellement la lecture --}}
        <div class="flex justify-center mt-10">
            <a href="{{ route('home') }}" class="btn-primary btn-lg gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ __('messages.results.run_another_test') }}
            </a>
        </div>
    @endif
</div>
@endsection

{{-- Le diagnostic DNS tourne en arrière-plan : on recharge dès qu'il a fini --}}
@if ($analysisPending ?? false)
@push('scripts')
<script>
(function () {
    const statusUrl = @json(route('test.status', $test->unique_id));

    // L'analyse prend quelques secondes ; on interroge sans précipitation.
    const timer = setInterval(async function () {
        try {
            const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
            const data = await response.json();

            if (data.domain_analyzed) {
                clearInterval(timer);
                window.location.reload();
            }
        } catch (e) {
            // On retentera au prochain tour.
        }
    }, 4000);
})();
</script>
@endpush
@endif

{{-- Popup Calendly : chargée uniquement quand les résultats sont affichés --}}
@if ($hasResults)
@push('head')
<link rel="stylesheet" href="https://assets.calendly.com/assets/external/widget.css">
<style>
    /* Fige la page derrière la popup, sans décalage dû à la scrollbar */
    body.calendly-popup-active {
        position: fixed;
        width: 100%;
        overflow: hidden;
    }
</style>
@endpush

@push('scripts')
<script src="https://assets.calendly.com/assets/external/widget.js" async></script>
<script>
(function () {
    'use strict';

    const trigger = document.querySelector('[data-calendly]');
    if (!trigger) return;

    const url = @json(config('mailsoar.expert_booking_url'));
    const visitorEmail = @json($test->visitor_email);
    const resultsUrl = @json(route('test.track', $test->unique_id));

    trigger.addEventListener('click', function (event) {
        // Sans le widget, on laisse le lien s'ouvrir normalement.
        if (typeof Calendly === 'undefined') return;

        event.preventDefault();

        const scrollY = window.scrollY;
        const scrollbar = window.innerWidth - document.documentElement.clientWidth;
        const basePadding = parseInt(window.getComputedStyle(document.body).paddingRight || '0', 10);

        document.body.style.paddingRight = (basePadding + scrollbar) + 'px';
        document.body.style.top = '-' + scrollY + 'px';
        document.body.classList.add('calendly-popup-active');

        Calendly.initPopupWidget({
            url: url,
            branding: false,
            prefill: {
                email: visitorEmail || '',
                customAnswers: {
                    // Champ « overview of the issue » : on y met le lien du test
                    a3: 'Test result: ' + resultsUrl,
                },
            },
            parentElement: document.body,
            utm: {},
        });

        // Restaure le défilement à la fermeture de la popup
        const watcher = setInterval(function () {
            if (document.querySelector('.calendly-overlay')) return;
            clearInterval(watcher);
            document.body.classList.remove('calendly-popup-active');
            document.body.style.top = '';
            document.body.style.paddingRight = '';
            window.scrollTo(0, scrollY);
        }, 300);
    });
})();
</script>
@endpush
@endif

@if (! $isFinished)
@push('scripts')
@php
    $jsLabels = [
        'inbox' => __('messages.results.inbox'),
        'spam' => __('messages.results.spam'),
        'promotions' => __('messages.results.promotions'),
        'updates' => __('messages.results.updates'),
        'received' => __('messages.test.received_count'),
        'waitingEmails' => __('messages.test.waiting_emails'),
        'ringWaiting' => __('messages.test.ring_waiting'),
        'ringAnalyzing' => __('messages.test.analyzing'),
        'refreshing' => __('messages.test.refreshing'),
        'statuses' => [
            __('messages.test.status_1'),
            __('messages.test.status_2'),
            __('messages.test.status_3'),
            __('messages.test.status_4'),
        ],
    ];

    $jsConfig = [
        'statusUrl' => route('test.status', $test->unique_id),
        'secondsLeft' => $secondsLeft,
        'timeoutSeconds' => config('mailsoar.email_check_timeout_minutes', 30) * 60,
        'knownResults' => $test->results->count(),
        'showingResults' => $hasResults,
        // Adresses rattachées à ce test, pour la copie et l'export
        'seeds' => $test->emailAccounts->map(fn ($a) => [
            'email' => $a->email,
            'provider' => $a->getRealProvider(),
        ])->values(),
    ];
@endphp
<script>
(function () {
    'use strict';

    const T = @json($jsLabels);
    const CFG = @json($jsConfig);

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ══════════ Résultats partiels : on recharge à chaque nouvelle réponse ══════════ */

    if (CFG.showingResults) {
        const note = document.querySelector('[data-refresh-note]');

        setInterval(async function () {
            let data;
            try {
                const response = await fetch(CFG.statusUrl, { headers: { 'Accept': 'application/json' } });
                data = await response.json();
            } catch (e) {
                return;
            }

            // Une réponse de plus, ou le test qui se clôt : on réaffiche
            if (data.received > CFG.knownResults || data.is_finished) {
                if (note) note.textContent = T.refreshing;
                window.location.reload();
            }
        }, 5000);
        return;
    }

    /* ══════════ Attente : aucun résultat pour l'instant ══════════ */

    const RING = 2 * Math.PI * 110;
    const ring      = document.querySelector('[data-ring]');
    const ringLabel = document.querySelector('[data-ring-label]');
    const clock     = document.querySelector('[data-countdown]');
    const status    = document.querySelector('[data-status]');
    const feed      = document.querySelector('[data-feed]');
    const feedDot   = document.querySelector('[data-feed-dot]');
    const feedCnt   = document.querySelector('[data-feed-count]');

    let left = CFG.secondsLeft;
    let shownStatus = -1;

    function tick() {
        const remaining = Math.max(0, left);
        clock.textContent = Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0');

        const elapsed = (CFG.timeoutSeconds - remaining) / CFG.timeoutSeconds;
        ring.style.strokeDashoffset = RING * (1 - Math.min(1, Math.max(0, elapsed)));

        const index = Math.min(T.statuses.length - 1, Math.floor(elapsed * T.statuses.length));
        if (index !== shownStatus) {
            shownStatus = index;
            status.style.opacity = '0';
            setTimeout(function () {
                status.textContent = T.statuses[index];
                status.style.opacity = '1';
            }, 150);
        }

        left -= 1;
    }
    tick();
    setInterval(tick, 1000);

    function placementLabel(placement) {
        switch (placement) {
            case 'inbox':      return [T.inbox, 'text-emerald-500', 'bg-emerald-50', 'text-emerald-600'];
            case 'promotions': return [T.promotions, 'text-blue-500', 'bg-blue-50', 'text-blue-600'];
            case 'updates':    return [T.updates, 'text-blue-500', 'bg-blue-50', 'text-blue-600'];
            default:           return [T.spam, 'text-amber-500', 'bg-amber-50', 'text-amber-600'];
        }
    }

    function renderFeed(accounts) {
        const arrived = (accounts || []).filter(function (a) { return a.received; });

        feedDot.className = 'w-2 h-2 rounded-full ' +
            (arrived.length ? 'bg-emerald-500 animate-pulse' : 'bg-muted-foreground');

        if (!arrived.length) {
            feed.innerHTML = '<p class="text-sm text-muted-foreground py-4 text-center">' + esc(T.waitingEmails) + '</p>';
            return;
        }

        feed.innerHTML = arrived.map(function (a) {
            const isInbox = a.placement === 'inbox';
            const tone = placementLabel(a.placement);
            const icon = isInbox
                ? '<path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>'
                : '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-2.99l-6.93-12a2 2 0 00-3.48 0l-6.93 12A2 2 0 005.07 19z"/>';

            return '<div class="flex items-center gap-2.5 text-sm">' +
                '<svg class="w-4 h-4 shrink-0 ' + tone[1] + '" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">' + icon + '</svg>' +
                '<svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                '<span class="font-medium truncate flex-1">' + esc(a.provider) + '</span>' +
                '<span class="text-xs px-1.5 py-0.5 rounded shrink-0 ' + tone[2] + ' ' + tone[3] + '">' + esc(tone[0]) + '</span>' +
            '</div>';
        }).join('');
    }

    let switching = false;

    async function poll() {
        if (switching) return;

        let data;
        try {
            const response = await fetch(CFG.statusUrl, { headers: { 'Accept': 'application/json' } });
            data = await response.json();
        } catch (e) {
            return; // nouvelle tentative au prochain tour
        }

        feedCnt.textContent = data.received + '/' + data.expected + ' ' + T.received;
        renderFeed(data.accounts);

        // Premier email détecté : on marque l'analyse, puis on affiche les
        // résultats sans attendre les autres réponses.
        if (data.received > 0) {
            switching = true;
            ringLabel.textContent = T.ringAnalyzing;
            setTimeout(function () { window.location.reload(); }, 1200);
            return;
        }

        if (data.is_finished) {
            window.location.reload();
        }
    }

    poll();
    setInterval(poll, 5000);

    /* ── rattrapage : recopier l'ID ou la liste d'adresses ── */

    /** Bascule « copier » → « copié », puis retour au bout de 2 s. */
    function flash(button) {
        const idle = button.querySelector('[data-copy-idle]');
        const done = button.querySelector('[data-copy-done]');
        idle.classList.replace('inline-flex', 'hidden');
        done.classList.replace('hidden', 'inline-flex');
        setTimeout(function () {
            idle.classList.replace('hidden', 'inline-flex');
            done.classList.replace('inline-flex', 'hidden');
        }, 2000);
    }

    function downloadCsv() {
        const rows = ['Email Address,Provider'].concat((CFG.seeds || []).map(function (s) {
            return s.email + ',' + (s.provider || '');
        }));
        const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'inbox-placement-seed-list.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const text = button.dataset.copy === 'tracking'
                ? document.querySelector('[data-tracking-id]').textContent.trim()
                : (CFG.seeds || []).map(function (s) { return s.email; }).join('\n');
            try {
                await navigator.clipboard.writeText(text);
                flash(button);
            } catch (e) {
                // Presse-papier refusé : on bascule sur le téléchargement
                if (button.dataset.copy === 'seeds') downloadCsv();
            }
        });
    });

    document.querySelector('[data-download-csv]').addEventListener('click', function () {
        downloadCsv();
        this.querySelector('[data-dl-idle]').classList.add('hidden');
        this.querySelector('[data-dl-done]').classList.remove('hidden');
    });
})();
</script>
@endpush
@endif

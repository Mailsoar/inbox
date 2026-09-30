@extends('layouts.public')

@section('title')
@if(app()->getLocale() === 'fr')
Inbox — Test gratuit de délivrabilité email par MailSoar
@else
Inbox — Free email inbox placement test by MailSoar
@endif
@endsection

@section('meta')
@if(app()->getLocale() === 'fr')
<meta name="description" content="Testez gratuitement la délivrabilité de vos emails. Vérifiez le placement dans la boîte de réception ou dans les spams, les protocoles SPF/DKIM/DMARC, la réputation IP et améliorez les performances de vos campagnes d'email marketing.">
@else
<meta name="description" content="Run a free email deliverability test. Check inbox vs spam placement, SPF/DKIM/DMARC, IP reputation, and boost your email deliverability campaign performance.">
@endif
@endsection

@php
    // Fournisseurs réellement couverts, pour ne rien annoncer d'inexact.
    // On en cite au plus quatre afin de garder la phrase lisible.
    // Les fournisseurs les plus reconnaissables passent devant : ce sont eux qui
    // rassurent le visiteur, pas les messageries régionales.
    $priority = ['Gmail', 'Google Workspace', 'Microsoft 365', 'Outlook / Hotmail', 'Outlook', 'Yahoo Mail', 'Yahoo'];

    $providerNames = collect($seedList)
        ->pluck('provider')
        ->unique()
        ->sortBy(function ($name) use ($priority) {
            $rank = array_search($name, $priority, true);
            return $rank === false ? count($priority) : $rank;
        })
        ->values();

    $shown = $providerNames->take(4);
    $truncated = $providerNames->count() > $shown->count();

    if ($truncated) {
        // Liste écourtée : virgules puis « et plus », sans esperluette
        $providers = $shown->implode(', ') . ' ' . __('messages.test.and_more');
    } elseif ($shown->count() > 1) {
        $providers = $shown->slice(0, -1)->implode(', ') . ' & ' . $shown->last();
    } else {
        $providers = $shown->first();
    }

    $seedCount = count($seedList);
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10 sm:py-16">

    {{-- ══════════ ÉTAT 1 : préparation et lancement ══════════ --}}
    <section data-state="idle">

        <div class="flex justify-center mb-6">
            @include('partials.logo', ['class' => 'h-10 w-auto'])
        </div>

        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-accent/10 text-accent text-sm font-medium mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                {{ __('messages.home.page_title') }}
            </div>
            <h1 class="text-3xl sm:text-4xl font-bold font-heading tracking-tight">
                {{ __('messages.home.title') }}
            </h1>
            <p class="mt-4 text-muted-foreground text-lg max-w-xl mx-auto">
                {{ __('messages.home.hero_description', ['providers' => $providers]) }}
            </p>
        </div>

        {{-- Rappel des trois étapes --}}
        @php
            // Icônes de la maquette : téléchargement, dièse, graphique
            $stepIcons = [
                1 => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4',
                2 => 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14',
                3 => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
            ];
        @endphp
        <div class="grid sm:grid-cols-3 gap-3 mb-10">
            @foreach ([1, 2, 3] as $i)
                <div class="border border-border rounded-xl p-4 bg-card/80 backdrop-blur">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-primary text-primary-foreground flex items-center justify-center font-bold text-sm">
                            {{ $i }}
                        </div>
                        <svg class="w-5 h-5 text-muted-foreground" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stepIcons[$i] }}"/>
                        </svg>
                    </div>
                    <div class="font-semibold text-sm">{{ __("messages.home.how_it_works_step{$i}_title") }}</div>
                    <div class="text-xs text-muted-foreground mt-1 leading-relaxed">
                        {{ __("messages.home.how_it_works_step{$i}_desc", ['providers' => $providers, 'count' => $seedCount]) }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card p-6 sm:p-8 shadow-sm">

            {{-- ÉTAPE 1 — Seed list : uniquement copie et téléchargement --}}
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3 flex-wrap gap-3">
                    <h2 class="font-semibold text-lg">
                        {{ __('messages.test.step_one') }} — {{ __('messages.test.test_addresses_list') }}
                    </h2>
                    <div class="flex flex-col items-start gap-2">
                        <button type="button" class="btn-primary btn-lg w-full sm:w-auto gap-2" data-copy="seeds">
                            <span data-copy-idle class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                </svg>
                                {{ __('messages.test.copy_seed_list') }}
                            </span>
                            <span data-copy-done class="hidden items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
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
                </div>
                <p class="text-sm text-muted-foreground">{{ __('messages.test.seed_list_help') }}</p>
            </div>

            {{-- ÉTAPE 2 — Identifiant de suivi --}}
            <div class="border-t border-border pt-6">
                <h2 class="font-semibold text-lg mb-1 flex items-center gap-2">
                    <svg class="w-5 h-5 text-muted-foreground" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                    </svg>
                    {{ __('messages.test.step_two') }} — {{ __('messages.test.unique_id') }}
                </h2>
                <p class="text-sm text-muted-foreground mb-4">{{ __('messages.test.tracking_id_help') }}</p>
                <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    <code data-tracking-id
                          class="flex-1 px-4 py-3 rounded-lg bg-muted font-mono text-base tracking-wider select-all border border-border text-center sm:text-left">{{ $trackingId }}</code>
                    <button type="button" class="btn-outline btn-lg gap-2 shrink-0" data-copy="tracking">
                        <span data-copy-idle class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                            </svg>
                            {{ __('messages.test.copy_id') }}
                        </span>
                        <span data-copy-done class="hidden items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ __('messages.test.copied') }}
                        </span>
                    </button>
                </div>
            </div>

            {{-- ÉTAPE 3 — Email, qui débloque le lancement --}}
            <div class="border-t border-border mt-6 pt-6">
                <h2 class="font-semibold text-lg mb-1 flex items-center gap-2">
                    <svg class="w-5 h-5 text-muted-foreground" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M3 8v8a2 2 0 002 2h14a2 2 0 002-2V8M3 8l9-5 9 5"/>
                    </svg>
                    {{ __('messages.test.step_three') }} — {{ __('messages.home.email_label') }}
                </h2>
                <p class="text-sm text-muted-foreground mb-4">{{ __('messages.home.email_help') }}</p>

                <form id="test-form" novalidate>
                    @csrf
                    <input type="email" id="visitor_email" name="visitor_email" class="input"
                           placeholder="{{ __('messages.home.email_placeholder') }}"
                           autocomplete="email" required>

                    <label class="mt-4 flex items-start gap-3 text-sm text-muted-foreground cursor-pointer">
                        <input type="checkbox" id="marketing_consent" name="marketing_consent" value="1"
                               class="mt-1 h-4 w-4 shrink-0 cursor-pointer" style="accent-color: hsl(var(--primary))">
                        <span>{{ __('messages.home.marketing_consent') }}</span>
                    </label>

                    <div data-error class="hidden rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive mt-4"></div>

                    <div class="mt-6 flex flex-col sm:flex-row gap-3 items-center">
                        <p class="text-xs text-muted-foreground flex-1">
                            {{ __('messages.test.start_hint') }}
                        </p>
                        <button type="submit" class="btn-primary btn-lg gap-2 w-full sm:w-auto" disabled>
                            <span data-submit-label>{{ __('messages.home.start_free_test') }}</span>
                            <svg data-arrow class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                            <svg data-spinner class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

</div>
@endsection

@push('scripts')
@php
    $jsConfig = [
        'storeUrl' => route('test.store'),
        'siteKey' => config('services.recaptcha.site_key'),
        'seeds' => $seedList,
        'locale' => app()->getLocale(),
    ];
    $jsLabels = [
        'creating' => __('messages.test.creating_test'),
        'start' => __('messages.home.start_free_test'),
    ];
@endphp
@if (config('services.recaptcha.site_key'))
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
@endif
<script>
(function () {
    'use strict';

    const T = @json($jsLabels);
    const CFG = @json($jsConfig);
    CFG.csrf = document.querySelector('meta[name="csrf-token"]').content;

    const form      = document.getElementById('test-form');
    const emailIn   = document.getElementById('visitor_email');
    const errorBox  = form.querySelector('[data-error]');
    const submitBtn = form.querySelector('button[type="submit"]');
    const spinner   = form.querySelector('[data-spinner]');
    const arrow     = form.querySelector('[data-arrow]');
    const label     = form.querySelector('[data-submit-label]');

    /* ── le bouton suit la validité de l'email ── */

    function refreshSubmitState() {
        // La phrase d'accroche reste toujours en place : la masquer ferait
        // sauter le bouton, qui partage sa rangée.
        submitBtn.disabled = !(emailIn.checkValidity() && emailIn.value.trim() !== '');
    }

    emailIn.addEventListener('input', refreshSubmitState);
    emailIn.addEventListener('blur', refreshSubmitState);
    refreshSubmitState();

    /* ── création du test ── */

    function recaptchaToken() {
        if (!CFG.siteKey || typeof grecaptcha === 'undefined') return Promise.resolve(null);
        return new Promise(function (resolve) {
            grecaptcha.ready(function () {
                grecaptcha.execute(CFG.siteKey, { action: 'create_test' })
                    .then(resolve).catch(function () { resolve(null); });
            });
        });
    }

    function setLoading(on) {
        submitBtn.disabled = on;
        spinner.classList.toggle('hidden', !on);
        arrow.classList.toggle('hidden', on);
        label.textContent = on ? T.creating : T.start;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        errorBox.classList.add('hidden');
        setLoading(true);

        try {
            const response = await fetch(CFG.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CFG.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    visitor_email: emailIn.value.trim(),
                    // « mixed » couvre toutes les boîtes, grand public et professionnelles
                    audience_type: 'mixed',
                    marketing_consent: document.getElementById('marketing_consent').checked,
                    'g-recaptcha-response': await recaptchaToken(),
                }),
            });
            const data = await response.json();

            if (!data.success) {
                errorBox.textContent = data.message || 'Error';
                errorBox.classList.remove('hidden');
                setLoading(false);
                refreshSubmitState();
                return;
            }

            // Le suivi vit à son URL propre : l'actualisation ne perd rien.
            window.location.href = '/' + data.test_id + '?lang=' + CFG.locale;
        } catch (error) {
            errorBox.textContent = error.message || 'Network error';
            errorBox.classList.remove('hidden');
            setLoading(false);
            refreshSubmitState();
        }
    });

    /* ── copie et export de la seed list ── */

    function addresses() {
        return (CFG.seeds || []).map(function (s) { return s.email; });
    }

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
                : addresses().join('\n');
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

{{--
    Carte en attente du diagnostic DNS.

    Même gabarit que la carte finale, pour que rien ne bouge quand les données
    arrivent : seuls les contenus variables sont remplacés par des blocs animés.
--}}
<div class="border border-border rounded-xl p-5 bg-card">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 bg-muted text-muted-foreground">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
                </svg>
            </div>
            <div class="min-w-0">
                <div class="font-semibold">{{ $label }}</div>
                <div class="text-xs text-muted-foreground">{{ $description }}</div>
            </div>
        </div>
        <span class="inline-flex items-center gap-1.5 text-sm font-medium px-2.5 py-1 rounded-full shrink-0 bg-muted text-muted-foreground">
            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            {{ __('messages.results.analysing') }}
        </span>
    </div>

    <div class="mt-4 space-y-2">
        <div class="h-3 w-3/5 rounded bg-muted animate-pulse"></div>
        <div class="h-8 w-full rounded-lg bg-muted/60 animate-pulse"></div>
    </div>
</div>

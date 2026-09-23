{{--
    Carte d'un contrôle sans donnée (aucun email reçu, ou aucune boîte n'a
    remonté ce résultat). Même gabarit que la carte finale : la section reste
    visible et le visiteur sait ce qui sera évalué.
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
            {{ __('messages.results.data_unavailable') }}
        </span>
    </div>

    <p class="mt-4 text-sm text-muted-foreground">{{ $reason }}</p>
</div>

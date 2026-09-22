@extends('layouts.public')

@section('title', __('messages.test.send_instructions') . ' — ' . $test->unique_id)

@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10 sm:py-16">
    <div class="text-center mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold font-heading">{{ __('messages.test.send_instructions') }}</h1>
    </div>

    <div class="card p-6 sm:p-8 shadow-sm space-y-8">
        {{-- Identifiant du test --}}
        <div>
            <h2 class="font-semibold text-lg mb-1">{{ __('messages.test.unique_id') }}</h2>
            <p class="text-sm text-muted-foreground mb-4">{{ __('messages.test.note_1') }}</p>
            <div class="flex flex-col sm:flex-row gap-3">
                <code id="tracking-id"
                      class="flex-1 px-4 py-3 rounded-lg bg-muted font-mono text-base tracking-wider select-all border border-border text-center sm:text-left">{{ $test->unique_id }}</code>
                <button type="button" class="btn-outline btn-lg gap-2" data-copy-target="#tracking-id">
                    <span data-copy-idle>{{ __('messages.test.copy_addresses') }}</span>
                    <span data-copy-done class="hidden">✓</span>
                </button>
            </div>
        </div>

        {{-- Adresses de test --}}
        <div class="border-t border-border pt-6">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-3">
                <h2 class="font-semibold text-lg">{{ __('messages.test.test_addresses_list') }}</h2>
                <button type="button" id="copy-seeds" class="btn-outline btn-sm gap-2">
                    <span data-copy-idle>{{ __('messages.test.copy_addresses') }}</span>
                    <span data-copy-done class="hidden">✓</span>
                </button>
            </div>
            <ul id="seed-list" class="rounded-lg border border-border divide-y divide-border overflow-hidden text-sm">
                @foreach ($test->emailAccounts as $account)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 bg-card">
                        <span class="font-mono text-xs sm:text-sm break-all" data-seed>{{ $account->email }}</span>
                        <span class="badge border-border bg-muted text-muted-foreground shrink-0 capitalize">
                            {{ $account->getRealProvider() }}
                        </span>
                    </li>
                @endforeach
            </ul>
            <p class="text-xs text-muted-foreground mt-3">{{ __('messages.test.note_2') }}</p>
        </div>

        <div class="border-t border-border pt-6 text-center">
            <a href="{{ route('test.results', $test->unique_id) }}" class="btn-primary btn-lg gap-2">
                {{ __('messages.test.view_results') }}
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function bindCopy(button, getText) {
        if (!button) return;
        button.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(getText());
            } catch (e) {
                return;
            }
            const idle = button.querySelector('[data-copy-idle]');
            const done = button.querySelector('[data-copy-done]');
            if (!idle || !done) return;
            idle.classList.add('hidden');
            done.classList.remove('hidden');
            setTimeout(function () {
                idle.classList.remove('hidden');
                done.classList.add('hidden');
            }, 2000);
        });
    }

    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        bindCopy(button, function () {
            return document.querySelector(button.dataset.copyTarget).textContent.trim();
        });
    });

    bindCopy(document.getElementById('copy-seeds'), function () {
        return Array.from(document.querySelectorAll('#seed-list [data-seed]'))
            .map(function (node) { return node.textContent.trim(); })
            .join('\n');
    });
})();
</script>
@endpush

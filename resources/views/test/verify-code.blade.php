@extends('layouts.public')

@section('title', __('messages.verification.title') . ' - Inbox by MailSoar')

@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
<div class="max-w-md mx-auto px-4 py-12 sm:py-20">
    <div class="card p-6 sm:p-8 shadow-sm">
        <div class="text-center mb-6">
            <div class="w-14 h-14 rounded-full bg-accent/10 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M3 8v8a2 2 0 002 2h14a2 2 0 002-2V8M3 8l9-5 9 5"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold font-heading">{{ __('messages.verification.title') }}</h1>
            <p class="text-sm text-muted-foreground mt-2">{{ __('messages.verification.code_sent', ['email' => $email]) }}</p>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive mb-4">
                @foreach ($errors->all() as $error)
                    <p class="mb-0">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('test.verify-code', ['email' => $email]) }}" class="space-y-5">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">

            <div>
                <label for="code" class="block text-sm font-medium mb-2">{{ __('messages.verification.enter_code') }}</label>
                <input type="text" name="code" id="code"
                       class="input text-center text-2xl tracking-[0.5em] font-mono"
                       placeholder="{{ __('messages.verification.code_placeholder') }}"
                       maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                       autocomplete="one-time-code" autofocus required>
                @error('code')
                    <p class="text-sm text-destructive mt-2">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary btn-lg w-full">
                {{ __('messages.verification.verify') }}
            </button>
        </form>

        <div class="mt-6 text-center space-y-2">
            <p class="text-xs text-muted-foreground">
                {{ __('messages.verification.code_expires', ['minutes' => 60]) }}
            </p>
            <button type="button" id="resendBtn" class="btn-ghost btn-sm text-accent" disabled>
                {{ __('messages.verification.resend') }}
            </button>
            <div id="countdown" class="text-xs text-muted-foreground"></div>
        </div>

        <div class="border-t border-border mt-6 pt-6 text-center">
            <a href="{{ route('test.request-access') }}" class="btn-outline btn-sm">
                {{ __('messages.general.back') }}
            </a>
        </div>
    </div>
</div>

<form id="resendForm" method="POST" action="{{ route('test.request-access') }}" class="hidden">
    @csrf
    <input type="hidden" name="email" value="{{ $email }}">
</form>
@endsection

@push('scripts')
<script>
(function () {
    const codeInput = document.getElementById('code');
    codeInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '');
    });

    const resendBtn = document.getElementById('resendBtn');
    const countdownEl = document.getElementById('countdown');
    const template = @json(__('messages.verification.resend_in', ['seconds' => ':seconds']));
    let remaining = 60;

    function tick() {
        if (remaining > 0) {
            countdownEl.textContent = template.replace(':seconds', remaining);
            remaining -= 1;
            setTimeout(tick, 1000);
        } else {
            countdownEl.textContent = '';
            resendBtn.disabled = false;
        }
    }
    tick();

    resendBtn.addEventListener('click', function () {
        document.getElementById('resendForm').submit();
    });
})();
</script>
@endpush

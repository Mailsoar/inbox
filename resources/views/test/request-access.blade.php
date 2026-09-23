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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold font-heading">{{ __('messages.verification.title') }}</h1>
            <p class="text-sm text-muted-foreground mt-2">{{ __('messages.verification.subtitle') }}</p>
        </div>

        @if (request()->get('expired'))
            <div class="rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm mb-4">
                {{ __('messages.verification.session_expired') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive mb-4">
                @foreach ($errors->all() as $error)
                    <p class="mb-0">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('test.request-access') }}" id="verificationForm" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium mb-2">{{ __('messages.home.email_label') }}</label>
                <input type="email" name="email" id="email" class="input"
                       placeholder="{{ __('messages.home.email_placeholder') }}"
                       value="{{ old('email') }}" autocomplete="email" required>
                @error('email')
                    <p class="text-sm text-destructive mt-2">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary btn-lg w-full gap-2" id="submitBtn">
                <span id="submitLabel">{{ __('messages.general.submit') }}</span>
                <svg id="submitSpinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </button>
        </form>

        <p class="text-xs text-muted-foreground text-center mt-6">
            {{ __('messages.general.support_contact') }}
        </p>
    </div>
</div>
@endsection

@push('scripts')
@if (config('services.recaptcha.site_key'))
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
@endif
<script>
(function () {
    const form    = document.getElementById('verificationForm');
    const button  = document.getElementById('submitBtn');
    const label   = document.getElementById('submitLabel');
    const spinner = document.getElementById('submitSpinner');
    const siteKey = @json(config('services.recaptcha.site_key'));

    form.addEventListener('submit', function (event) {
        // Sans reCAPTCHA configuré, on laisse le formulaire partir normalement.
        if (!siteKey || typeof grecaptcha === 'undefined') {
            return;
        }

        event.preventDefault();
        button.disabled = true;
        spinner.classList.remove('hidden');
        label.textContent = @json(__('messages.general.loading'));

        grecaptcha.ready(function () {
            grecaptcha.execute(siteKey, { action: 'verify_email' }).then(function (token) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'g-recaptcha-response';
                input.value = token;
                form.appendChild(input);
                form.submit();
            });
        });
    });
})();
</script>
@endpush

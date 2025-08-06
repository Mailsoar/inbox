@extends('layouts.app')

@section('title', __('messages.verification.title') . ' - Inbox by MailSoar')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-envelope-open-text fa-3x text-primary mb-3"></i>
                        <h2 class="h3 fw-bold mb-2">{{ __('messages.verification.title') }}</h2>
                        <p class="text-muted">{{ __('messages.verification.code_sent', ['email' => $email]) }}</p>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger">
                            @foreach($errors->all() as $error)
                                <p class="mb-0">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('test.verify-code', ['email' => $email]) }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        <div class="mb-4">
                            <label for="code" class="form-label">{{ __('messages.verification.enter_code') }}</label>
                            <input type="text" 
                                   name="code" 
                                   id="code"
                                   class="form-control form-control-lg text-center @error('code') is-invalid @enderror" 
                                   placeholder="{{ __('messages.verification.code_placeholder') }}"
                                   maxlength="6"
                                   pattern="[0-9]{6}"
                                   inputmode="numeric"
                                   autocomplete="off"
                                   autofocus
                                   required>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-check-circle me-2"></i>
                            {{ __('messages.verification.verify') }}
                        </button>
                    </form>

                    <div class="mt-4 text-center">
                        <p class="text-muted mb-2">
                            <i class="fas fa-clock"></i>
                            {{ __('messages.verification.code_expires', ['minutes' => 60]) }}
                        </p>
                        
                        <div id="resendSection">
                            <button type="button" 
                                    class="btn btn-link"
                                    id="resendBtn"
                                    onclick="resendCode()"
                                    disabled>
                                {{ __('messages.verification.resend') }}
                            </button>
                            <div id="countdown" class="text-muted small"></div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="text-center">
                        <a href="{{ route('test.request-access') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            {{ __('messages.general.back') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Format du code automatique
document.getElementById('code').addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '');
    // Suppression de l'auto-submit pour permettre de cocher la case
});

// Gestion du timer de renvoi
let countdown = 60; // 60 secondes avant de pouvoir renvoyer
const countdownElement = document.getElementById('countdown');
const resendBtn = document.getElementById('resendBtn');

function updateCountdown() {
    if (countdown > 0) {
        countdownElement.textContent = '{{ __("messages.verification.resend_in", ["seconds" => ""]) }}'.replace('', countdown);
        countdown--;
        setTimeout(updateCountdown, 1000);
    } else {
        countdownElement.textContent = '';
        resendBtn.disabled = false;
    }
}

updateCountdown();

function resendCode() {
    // Créer un formulaire pour renvoyer le code
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("test.request-access") }}';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = '{{ csrf_token() }}';
    
    const emailInput = document.createElement('input');
    emailInput.type = 'hidden';
    emailInput.name = 'email';
    emailInput.value = '{{ $email }}';
    
    form.appendChild(csrfInput);
    form.appendChild(emailInput);
    document.body.appendChild(form);
    form.submit();
}
</script>
@endsection
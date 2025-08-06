@extends('layouts.app')

@section('title', __('messages.verification.title') . ' - Inbox by MailSoar')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                        <h2 class="h3 fw-bold mb-2">{{ __('messages.verification.title') }}</h2>
                        <p class="text-muted">{{ __('messages.verification.subtitle') }}</p>
                    </div>

                    @if(request()->get('expired'))
                        <div class="alert alert-warning">
                            <i class="fas fa-clock me-2"></i>
                            {{ __('messages.verification.session_expired', [], app()->getLocale()) ?: 'Votre session a expiré. Veuillez vous reconnecter.' }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            @foreach($errors->all() as $error)
                                <p class="mb-0">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('test.request-access') }}" id="verificationForm">
                        @csrf
                        <div class="mb-4">
                            <label for="email" class="form-label">{{ __('messages.home.email_label') }}</label>
                            <input type="email" 
                                   name="email" 
                                   id="email"
                                   class="form-control form-control-lg @error('email') is-invalid @enderror" 
                                   placeholder="{{ __('messages.home.email_placeholder') }}"
                                   value="{{ old('email') }}"
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i>
                            {{ __('messages.general.submit') }}
                        </button>
                    </form>

                    <div class="mt-4 text-center">
                        <p class="text-muted small">
                            <i class="fas fa-info-circle"></i>
                            {{ __('messages.general.support_contact') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- reCAPTCHA v3 -->
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
<script>
document.getElementById('verificationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __("messages.general.loading") }}';
    
    grecaptcha.ready(function() {
        grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', {action: 'verify_email'})
            .then(function(token) {
                // Ajouter le token au formulaire
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = 'g-recaptcha-response';
                tokenInput.value = token;
                document.getElementById('verificationForm').appendChild(tokenInput);
                
                // Soumettre le formulaire
                document.getElementById('verificationForm').submit();
            });
    });
});
</script>

@endsection
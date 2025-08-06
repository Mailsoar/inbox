@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-shield-alt text-primary fa-3x mb-3"></i>
                        <h3 class="fw-bold">Administration MailSoar</h3>
                        <p class="text-muted">Connectez-vous avec votre compte Google autorisé</p>
                    </div>

                    @if(session('error'))
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="d-grid">
                        <a href="{{ route('admin.auth.google') }}" class="btn btn-danger btn-lg">
                            <i class="fab fa-google me-2"></i>
                            Se connecter avec Google
                        </a>
                    </div>

                    <hr class="my-4">

                    <div class="text-center">
                        <small class="text-muted">
                            <i class="fas fa-lock me-1"></i>
                            Accès réservé aux administrateurs autorisés
                        </small>
                        <div class="mt-3">
                            <a href="{{ route('home') }}" class="btn btn-link">
                                <i class="fas fa-arrow-left me-1"></i>
                                Retour au site
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
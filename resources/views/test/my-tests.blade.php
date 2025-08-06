@extends('layouts.app')

@section('title', __('messages.my_tests.page_title') . ' - Inbox by MailSoar')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-history fa-3x text-primary mb-3"></i>
                        <h2 class="h3 fw-bold mb-2">{{ __('messages.my_tests.title') }}</h2>
                        <p class="text-muted">{{ __('messages.my_tests.tests_for_email', ['email' => $email]) }}</p>
                        <p class="small text-muted">{{ __('messages.my_tests.showing_recent') }}</p>
                        
                        <!-- Informations sur les limites -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="alert alert-info h-100">
                                    <i class="fas fa-chart-line me-2"></i>
                                    <strong>{{ __('messages.my_tests.tests_remaining') }}</strong><br>
                                    {{ $emailRemaining }} / {{ $emailLimit }} {{ __('messages.my_tests.tests_available') }}
                                    @if($resetTime)
                                        <br>
                                        <small>{{ __('messages.my_tests.limit_reset') }} {{ $resetTime->diffForHumans() }}</small>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-warning h-100">
                                    <i class="fas fa-clock me-2"></i>
                                    <strong>{{ __('messages.my_tests.session_expires') }}</strong><br>
                                    <span id="sessionTimer">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($tests->isEmpty())
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>
                            {{ __('messages.my_tests.no_tests_found') }}
                        </div>
                        <div class="text-center">
                            <a href="{{ route('test.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>
                                {{ __('messages.my_tests.create_new_test') }}
                            </a>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Test ID</th>
                                        <th>Type</th>
                                        <th>Statut</th>
                                        <th>Progression</th>
                                        <th>Résultats</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tests as $test)
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-primary">#{{ $test['unique_id'] }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                {{ strtoupper($test['audience_type']) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($test['status'] === 'completed')
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    Terminé
                                                </span>
                                            @elseif($test['status'] === 'in_progress')
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-spinner fa-spin me-1"></i>
                                                    En cours
                                                </span>
                                            @elseif($test['status'] === 'cancelled')
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times-circle me-1"></i>
                                                    Annulé
                                                </span>
                                            @else
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-clock me-1"></i>
                                                    En attente
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress me-2" style="width: 100px; height: 8px;">
                                                    <div class="progress-bar" style="width: {{ $test['completion_rate'] }}%"></div>
                                                </div>
                                                <small class="text-muted">
                                                    {{ $test['received_count'] }}/{{ $test['total_accounts'] }}
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            @if($test['received_count'] > 0)
                                                <div>
                                                    <span class="badge bg-success" data-bs-toggle="tooltip" title="Inbox">
                                                        <i class="fas fa-inbox"></i> {{ $test['inbox_count'] }}
                                                    </span>
                                                    <span class="badge bg-danger" data-bs-toggle="tooltip" title="Spam">
                                                        <i class="fas fa-exclamation-triangle"></i> {{ $test['spam_count'] }}
                                                    </span>
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ \Carbon\Carbon::parse($test['created_at'])->format('d/m/Y H:i') }}
                                                <br>
                                                <span class="text-primary">
                                                    {{ \Carbon\Carbon::parse($test['created_at'])->diffForHumans() }}
                                                </span>
                                            </small>
                                        </td>
                                        <td>
                                            <a href="{{ route('test.results', $test['unique_id']) }}" 
                                               class="btn btn-sm btn-outline-primary"
                                               data-bs-toggle="tooltip" title="Voir les résultats">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 text-center">
                            <a href="{{ route('test.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>
                                {{ __('messages.my_tests.create_new_test') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- Boutons d'action -->
            <div class="mt-4 text-center">
                <a href="{{ route('test.trusted-devices') }}" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-laptop me-2"></i>
                    {{ __('messages.devices.title') }}
                </a>
                <button type="button" class="btn btn-outline-danger" onclick="logout()">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    {{ __('messages.my_tests.logout') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Initialiser les tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Timer de session
const sessionDuration = 12 * 60 * 60 * 1000; // 12 heures en millisecondes
@php
    $verifiedAt = session('verified_at');
    if ($verifiedAt instanceof \Carbon\Carbon) {
        $verifiedAtString = $verifiedAt->toIso8601String();
    } else {
        $verifiedAtString = $verifiedAt;
    }
@endphp
const verifiedAt = new Date('{{ $verifiedAtString }}').getTime();

function updateSessionTimer() {
    const now = new Date().getTime();
    const elapsed = now - verifiedAt;
    const remaining = sessionDuration - elapsed;
    
    if (remaining <= 0) {
        document.getElementById('sessionTimer').textContent = '{{ __("messages.my_tests.session_expired") }}';
        setTimeout(() => {
            window.location.href = '{{ route("test.request-access") }}';
        }, 2000);
        return;
    }
    
    const days = Math.floor(remaining / (1000 * 60 * 60 * 24));
    const hours = Math.floor((remaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
    
    let timeString = '';
    if (days > 0) {
        timeString += days + ' {{ __("messages.my_tests.days") }} ';
    }
    if (hours > 0 || days > 0) {
        timeString += hours + ' {{ __("messages.my_tests.hours") }} ';
    }
    timeString += minutes + ' {{ __("messages.my_tests.minutes") }}';
    
    document.getElementById('sessionTimer').textContent = timeString;
}

// Mettre à jour le timer toutes les secondes
setInterval(updateSessionTimer, 1000);
updateSessionTimer();

// Fonction de déconnexion
function logout() {
    if (confirm('{{ __("messages.general.confirm_logout") }}')) {
        // Créer un formulaire pour la déconnexion
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("test.logout") }}';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        
        form.appendChild(csrfInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endpush
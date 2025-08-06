@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <!-- Étapes de progression -->
            <div class="mb-5">
                <div class="d-flex justify-content-between">
                    <div class="text-center flex-fill">
                        <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
                            <i class="fas fa-check"></i>
                        </div>
                        <p class="small mb-0 text-muted">{{ __('messages.test.step_configuration') }}</p>
                    </div>
                    <div class="text-center flex-fill">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
                            <span>2</span>
                        </div>
                        <p class="small mb-0 fw-bold">{{ __('messages.instructions.sending') }}</p>
                    </div>
                    <div class="text-center flex-fill">
                        <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
                            <span>3</span>
                        </div>
                        <p class="small mb-0 text-muted">{{ __('messages.test.step_results') }}</p>
                    </div>
                </div>
            </div>

            <!-- Instructions principales -->
            <div class="card shadow-lg mb-4">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-envelope fa-2x"></i>
                        </div>
                        <h2 class="mb-3">{{ __('messages.instructions.title') }}</h2>
                        <p class="lead text-muted">
                            {{ __('messages.instructions.test_ready', ['id' => $test->unique_id]) }}
                        </p>
                    </div>

                    <div class="alert alert-info">
                        <h5 class="alert-heading"><i class="fas fa-info-circle"></i> {{ __('messages.instructions.how_to_proceed') }} :</h5>
                        <ol class="mb-0">
                            <li class="mb-2">Utilisez votre plateforme d'envoi d'emails habituelle (ESP, serveur SMTP, etc.)</li>
                            <li class="mb-2">
                                <strong>IMPORTANT :</strong> Incluez l'identifiant <code class="bg-warning px-2 py-1 rounded">{{ $test->unique_id }}</code> 
                                dans le sujet OU le corps de votre email
                            </li>
                            <li class="mb-2">Envoyez votre email aux {{ $test->emailAccounts->count() }} adresses listées ci-dessous</li>
                            <li>Attendez 5-10 minutes pour la collecte des résultats</li>
                        </ol>
                    </div>

                    <!-- Identifiant unique -->
                    <div class="text-center my-4">
                        <div class="border border-primary rounded p-4 bg-light">
                            <h5 class="text-primary mb-2">Votre identifiant unique</h5>
                            <div class="d-flex align-items-center justify-content-center">
                                <h1 class="mb-0 font-monospace text-primary me-3" id="testId">{{ $test->unique_id }}</h1>
                                <button class="btn btn-outline-primary" onclick="copyToClipboard('{{ $test->unique_id }}')">
                                    <i class="fas fa-copy"></i> Copier
                                </button>
                            </div>
                            <small class="text-muted d-block mt-2">Cliquez pour copier dans le presse-papier</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des emails -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Adresses email de test ({{ $test->emailAccounts->count() }})
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="mb-0 text-muted">Envoyez votre email à toutes ces adresses :</p>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-2" onclick="copyAllEmails()">
                                <i class="fas fa-copy"></i> Copier toutes
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="downloadEmailList()">
                                <i class="fas fa-download"></i> Télécharger CSV
                            </button>
                        </div>
                    </div>

                    <div class="row" id="emailList">
                        @foreach($test->emailAccounts->groupBy('provider') as $provider => $accounts)
                        <div class="col-md-4 mb-3">
                            <h6 class="text-muted mb-2">
                                @if($provider === 'gmail')
                                    <i class="fab fa-google text-danger"></i> Gmail
                                @elseif($provider === 'outlook')
                                    <i class="fab fa-microsoft text-primary"></i> Outlook
                                @elseif($provider === 'yahoo')
                                    <i class="fab fa-yahoo text-purple"></i> Yahoo
                                @else
                                    <i class="fas fa-envelope"></i> {{ ucfirst($provider) }}
                                @endif
                                ({{ $accounts->count() }})
                            </h6>
                            <div class="list-group">
                                @foreach($accounts as $account)
                                <div class="list-group-item list-group-item-action py-2 px-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="font-monospace">{{ $account->email }}</small>
                                        <button class="btn btn-sm btn-link p-0" onclick="copyToClipboard('{{ $account->email }}')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important :</strong> Envoyez à toutes les adresses pour des résultats fiables. 
                        Les emails manquants seront considérés comme non reçus.
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="text-center">
                <a href="{{ route('test.results', $test->unique_id) }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-chart-line"></i> Voir les résultats
                </a>
                <p class="text-muted mt-2">
                    Les résultats se mettent à jour automatiquement toutes les 30 secondes
                </p>
            </div>

            <!-- Conseils -->
            <div class="row mt-5">
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-clock text-info mb-2" style="font-size: 2rem;"></i>
                        <h6>Timing optimal</h6>
                        <p class="small text-muted">
                            Attendez 5-10 minutes après l'envoi pour des résultats complets
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-redo text-success mb-2" style="font-size: 2rem;"></i>
                        <h6>Mise à jour automatique</h6>
                        <p class="small text-muted">
                            Les résultats se rafraîchissent automatiquement
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-shield-alt text-warning mb-2" style="font-size: 2rem;"></i>
                        <h6>Données sécurisées</h6>
                        <p class="small text-muted">
                            Test supprimé automatiquement après 7 jours
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Afficher un toast ou une notification
        showNotification('{{ __("messages.test.copied_clipboard") }}');
    }, function(err) {
        console.error('Erreur lors de la copie :', err);
    });
}

function copyAllEmails() {
    const emails = Array.from(document.querySelectorAll('#emailList .font-monospace'))
        .map(el => el.textContent.trim())
        .join(', ');
    
    copyToClipboard(emails);
}

function downloadEmailList() {
    const emails = Array.from(document.querySelectorAll('#emailList .font-monospace'))
        .map(el => el.textContent.trim());
    
    const csv = 'Email\n' + emails.join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'test_{{ $test->unique_id }}_emails.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function showNotification(message) {
    // Créer une notification temporaire
    const notification = document.createElement('div');
    notification.className = 'position-fixed top-0 end-0 m-3 alert alert-success alert-dismissible fade show';
    notification.innerHTML = `
        <i class="fas fa-check-circle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>
@endpush

<style>
.font-monospace {
    font-family: 'Courier New', Courier, monospace;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

.btn-link {
    text-decoration: none;
}

.text-purple {
    color: #6f42c1;
}
</style>
@endsection
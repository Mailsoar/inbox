@extends('layouts.admin')

@section('title', 'Modifier le compte - ' . $emailAccount->email)

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.email-accounts.index') }}">Comptes Email</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $emailAccount->email }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            @if(!$emailAccount->is_active && $emailAccount->connection_status === 'success')
                Finaliser la configuration du compte
            @else
                Modifier le compte email
            @endif
        </h1>
    </div>

    {{-- Alerts --}}
    @if(!$emailAccount->is_active && $emailAccount->connection_status === 'success')
        <div class="alert alert-warning" role="alert">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="alert-heading mb-2"><i class="fas fa-exclamation-triangle"></i> Configuration requise</h5>
                    <p class="mb-0">L'authentification a réussi. Veuillez maintenant finaliser la configuration pour activer le compte.</p>
                </div>
                <div>
                    <a href="{{ route('admin.email-accounts.configure-antispam', $emailAccount) }}" class="btn btn-warning">
                        <i class="fas fa-cogs"></i> Finaliser la configuration pour activer le compte
                    </a>
                </div>
            </div>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        {{-- Colonne principale --}}
        <div class="col-lg-8">
            {{-- Formulaire de modification --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informations du compte</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.email-accounts.update', $emailAccount) }}" method="POST" 
                        id="editForm" onsubmit="handleFormSubmit(event)">
                        @csrf
                        @method('PUT')
                        
                        {{-- Email (readonly) --}}
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="{{ $emailAccount->email }}" readonly>
                        </div>

                        {{-- Provider et Type de compte sur la même ligne --}}
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Fournisseur</label>
                                <div>
                                    <span class="badge 
                                        @if($emailAccount->provider === 'gmail') bg-danger
                                        @elseif($emailAccount->provider === 'outlook') bg-primary
                                        @elseif($emailAccount->provider === 'yahoo') bg-purple
                                        @else bg-secondary
                                        @endif">
                                        {{ $emailAccount->getProviderDisplayName() }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Type de compte</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="account_type" id="account_type_hidden" value="{{ old('account_type', $emailAccount->account_type) }}">
                                    <input class="form-check-input" type="checkbox" role="switch" id="account_type_switch" 
                                        {{ old('account_type', $emailAccount->account_type) === 'b2b' ? 'checked' : '' }}
                                        onchange="updateAccountType()">
                                    <label class="form-check-label" for="account_type_switch">
                                        <span id="account_type_label">
                                            {{ old('account_type', $emailAccount->account_type) === 'b2b' ? 'B2B - Professionnel' : 'B2C - Grand public' }}
                                        </span>
                                        <span class="badge bg-warning ms-2 d-none" id="type-recommendation-badge">
                                            <i class="fas fa-exclamation-triangle"></i> Changement recommandé
                                        </span>
                                    </label>
                                </div>
                                <small class="form-text text-muted d-none" id="type-recommendation-reason"></small>
                            </div>
                        </div>

                        {{-- Nom --}}
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom d'affichage</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                name="name" id="name" value="{{ old('name', $emailAccount->name) }}">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Statut actif - uniquement si déjà configuré --}}
                        @if($emailAccount->is_active || !$emailAccount->connection_status || $emailAccount->connection_status !== 'success')
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" 
                                    id="is_active" value="1" 
                                    {{ old('is_active', $emailAccount->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Compte actif
                                </label>
                            </div>
                        </div>
                        @endif

                        @if($emailAccount->is_active || !$emailAccount->connection_status || $emailAccount->connection_status !== 'success')
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> Enregistrer
                            </button>
                            <a href="{{ route('admin.email-accounts.index') }}" class="btn btn-outline-secondary">
                                Annuler
                            </a>
                        </div>
                        @endif
                    </form>
                </div>
            </div>

            {{-- Configuration Anti-spam et Mapping des dossiers --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Configuration Anti-spam et Dossiers</h5>
                    <a href="{{ route('admin.email-accounts.configure-antispam', $emailAccount) }}" 
                        class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-edit"></i> Modifier
                    </a>
                </div>
                <div class="card-body">
                    {{-- Systèmes anti-spam --}}
                    <div class="mb-3">
                        <label class="text-muted small">Systèmes anti-spam</label>
                        <div>
                            @if($emailAccount->antispamSystems->count() > 0)
                                @foreach($emailAccount->antispamSystems as $system)
                                    <span class="badge bg-info me-1">
                                        <i class="fas fa-shield-alt"></i> {{ $system->display_name }}
                                    </span>
                                @endforeach
                            @else
                                <span class="badge bg-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Aucun système configuré
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Mapping des dossiers --}}
                    <div class="mb-3">
                        <label class="text-muted small">Mapping des dossiers</label>
                        <div>
                            @if($emailAccount->folderMappings->count() > 0)
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($emailAccount->folderMappings()->orderBy('sort_order')->get() as $mapping)
                                        @php
                                            $badgeClass = match($mapping->folder_type) {
                                                'inbox' => 'bg-success',
                                                'spam' => 'bg-danger',
                                                'additional_inbox' => 'bg-info',
                                                default => 'bg-secondary'
                                            };
                                            $iconClass = match($mapping->folder_type) {
                                                'inbox' => 'fa-inbox',
                                                'spam' => 'fa-ban',
                                                'additional_inbox' => 'fa-folder-plus',
                                                default => 'fa-folder'
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">
                                            <i class="fas {{ $iconClass }}"></i> 
                                            {{ $mapping->display_name ?? ucfirst($mapping->folder_type) }}: 
                                            {{ $mapping->folder_name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="badge bg-secondary">
                                    <i class="fas fa-folder"></i> Aucun mapping configuré
                                </span>
                            @endif
                        </div>
                    </div>


                    {{-- Message d'information si non configuré --}}
                    @if($emailAccount->antispamSystems->count() == 0 || $emailAccount->folderMappings->count() == 0)
                        <div class="alert alert-warning alert-sm mb-0 mt-3">
                            <i class="fas fa-info-circle"></i> 
                            La configuration anti-spam n'est pas complète. 
                            <a href="{{ route('admin.email-accounts.configure-antispam', $emailAccount) }}" class="alert-link">
                                Configurer maintenant
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Emails récents --}}
            @if($recentEmails->count() > 0)
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Emails récents</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Test ID</th>
                                    <th>Placement</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentEmails as $email)
                                <tr>
                                    <td class="fw-bold">{{ $email->unique_id }}</td>
                                    <td>
                                        <span class="badge 
                                            @if($email->placement === 'inbox') bg-success
                                            @elseif($email->placement === 'spam') bg-danger
                                            @elseif($email->placement === 'promotions') bg-warning
                                            @else bg-secondary
                                            @endif">
                                            {{ ucfirst($email->placement) }}
                                        </span>
                                    </td>
                                    <td>{{ $email->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Colonne latérale --}}
        <div class="col-lg-4">
            {{-- Statistiques --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Statistiques</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Total des emails</label>
                        <h3 class="mb-0">{{ $stats['total_emails'] }}</h3>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Taux Inbox</label>
                        <h4 class="mb-0 text-success">{{ $stats['inbox_rate'] }}%</h4>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Taux Spam</label>
                        <h4 class="mb-0 text-danger">{{ $stats['spam_rate'] }}%</h4>
                    </div>
                    
                    @if($stats['last_email'])
                    <div>
                        <label class="text-muted small">Dernier email</label>
                        <p class="mb-0">{{ $stats['last_email']->created_at->diffForHumans() }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Test de connexion --}}
            <div class="card shadow-sm mb-4 @if(!$emailAccount->is_active && $emailAccount->connection_status === 'success') border-warning @endif">
                <div class="card-header @if(!$emailAccount->is_active && $emailAccount->connection_status === 'success') bg-warning text-dark @endif">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-plug"></i> Connexion
                    </h5>
                </div>
                <div class="card-body">
                    @if($emailAccount->last_connection_status)
                    <div class="mb-3">
                        <label class="text-muted small">Statut</label>
                        <div>
                            <span class="badge 
                                @if($emailAccount->last_connection_status === 'success') bg-success
                                @elseif($emailAccount->last_connection_status === 'failed') bg-danger
                                @else bg-warning
                                @endif">
                                <i class="fas @if($emailAccount->last_connection_status === 'success') fa-check-circle @elseif($emailAccount->last_connection_status === 'failed') fa-times-circle @else fa-question-circle @endif"></i>
                                @if($emailAccount->last_connection_status === 'success')
                                    Connexion OK
                                @elseif($emailAccount->last_connection_status === 'failed')
                                    Échec de connexion
                                @else
                                    Statut inconnu
                                @endif
                            </span>
                        </div>
                    </div>
                    @endif
                    
                    @if($emailAccount->connection_error)
                    <div class="alert alert-danger mb-3 p-2">
                        <small>
                            <i class="fas fa-exclamation-circle"></i> {{ $emailAccount->connection_error }}
                        </small>
                    </div>
                    @endif
                    
                    @if($emailAccount->last_connection_check)
                    <div class="mb-3">
                        <label class="text-muted small">Dernier test</label>
                        <p class="mb-0">{{ $emailAccount->last_connection_check->format('d/m/Y H:i') }}</p>
                    </div>
                    @endif
                    
                    <button type="button" class="btn btn-primary w-100" id="testConnectionBtn" onclick="testConnection()">
                        <i class="fas fa-sync-alt"></i> Tester la connexion
                    </button>
                    
                    <div id="testResult" class="mt-3" style="display: none;">
                        <div class="alert" role="alert">
                            <span id="testResultMessage"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Authentification --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-key"></i> Authentification
                    </h5>
                </div>
                <div class="card-body">
                    @if($emailAccount->auth_type === 'oauth' || $emailAccount->oauth_token)
                    <!-- OAuth Authentication -->
                    <div class="mb-3">
                        <label class="text-muted small">Type d'authentification</label>
                        <div>
                            <span class="badge bg-info">
                                <i class="fas fa-shield-alt"></i> OAuth 2.0
                            </span>
                        </div>
                    </div>
                    
                    @if($emailAccount->oauth_token)
                    <div class="mb-3">
                        <label class="text-muted small">Token OAuth</label>
                        <div>
                            <span class="badge bg-success">
                                <i class="fas fa-check"></i> Token présent
                            </span>
                        </div>
                    </div>
                    @endif
                    
                    @if($emailAccount->last_token_refresh)
                    <div class="mb-3">
                        <label class="text-muted small">Dernier refresh</label>
                        <p class="mb-0">{{ $emailAccount->last_token_refresh->format('d/m/Y H:i') }}</p>
                    </div>
                    @endif
                    
                    @if($emailAccount->provider === 'gmail')
                    <a href="{{ route('oauth.gmail.connect', ['account_id' => $emailAccount->id]) }}" 
                       class="btn btn-warning w-100 mb-2">
                        <i class="fas fa-sync"></i> Réauthentifier avec Gmail OAuth
                    </a>
                    @elseif($emailAccount->provider === 'outlook')
                    <a href="{{ route('oauth.microsoft.connect', ['account_id' => $emailAccount->id]) }}" 
                       class="btn btn-warning w-100 mb-2">
                        <i class="fas fa-sync"></i> Réauthentifier avec Microsoft OAuth
                    </a>
                    @endif
                    
                    @else
                    <!-- Password Authentication -->
                    <div class="mb-3">
                        <label class="text-muted small">Type d'authentification</label>
                        <div>
                            <span class="badge bg-secondary">
                                <i class="fas fa-lock"></i> Mot de passe
                            </span>
                        </div>
                    </div>
                    
                    @if($emailAccount->password || $emailAccount->imap_password)
                    <div class="mb-3">
                        <label class="text-muted small">Mot de passe</label>
                        <div>
                            <span class="badge bg-success">
                                <i class="fas fa-check"></i> Configuré
                            </span>
                        </div>
                    </div>
                    @endif
                    
                    <button type="button" class="btn btn-warning w-100" onclick="showPasswordModal()">
                        <i class="fas fa-key"></i> Changer le mot de passe
                    </button>
                    @endif
                </div>
            </div>

            {{-- Actions dangereuses --}}
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">Zone dangereuse</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Cette action est irréversible.</p>
                    <form action="{{ route('admin.email-accounts.destroy', $emailAccount) }}" method="POST" 
                        data-confirm="Êtes-vous sûr de vouloir supprimer définitivement le compte {{ $emailAccount->email }} ? Cette action est irréversible et toutes les données associées seront perdues.">
                        @csrf
                        @method('DELETE')
                        
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="fas fa-trash"></i> Supprimer le compte
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal pour changer le mot de passe --}}
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Changer le mot de passe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.email-accounts.update-password', $emailAccount) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nouveau mot de passe</label>
                        <input type="password" class="form-control" name="password" id="new_password" required>
                        <small class="form-text text-muted">
                            Pour les comptes Outlook/Yahoo, utilisez un mot de passe d'application si la 2FA est activée.
                        </small>
                    </div>
                    
                    @if($emailAccount->provider === 'imap')
                    <div class="mb-3">
                        <label for="imap_password" class="form-label">Mot de passe IMAP (optionnel)</label>
                        <input type="password" class="form-control" name="imap_password" id="imap_password">
                        <small class="form-text text-muted">
                            Laissez vide pour utiliser le même mot de passe pour IMAP.
                        </small>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Custom styles --}}
<style>
.bg-purple {
    background-color: #6f42c1 !important;
}

/* Style amélioré pour le switch */
.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.form-switch .form-check-input {
    width: 3em;
    height: 1.5em;
    cursor: pointer;
}

.form-switch .form-check-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Animation pour attirer l'attention sur le switch */
@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(255, 193, 7, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0);
    }
}

.form-check-input.border-warning {
    animation: pulse 2s infinite;
}
</style>

<script>
function testConnection() {
    const button = document.getElementById('testConnectionBtn');
    const resultDiv = document.getElementById('testResult');
    const resultAlert = resultDiv.querySelector('.alert');
    const resultMessage = document.getElementById('testResultMessage');
    
    // Désactiver le bouton et afficher un spinner
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Test en cours...';
    resultDiv.style.display = 'none';
    
    // Faire la requête AJAX
    fetch('{{ route("admin.email-accounts.test", $emailAccount) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        // Réactiver le bouton
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-sync-alt"></i> Tester la connexion';
        
        // Afficher le résultat
        resultDiv.style.display = 'block';
        
        if (data.success) {
            resultAlert.className = 'alert alert-success';
            resultMessage.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
        } else {
            resultAlert.className = 'alert alert-danger';
            resultMessage.innerHTML = '<i class="fas fa-times-circle"></i> ' + data.message;
        }
        
        // Masquer le résultat après 5 secondes
        setTimeout(() => {
            resultDiv.style.display = 'none';
        }, 5000);
    })
    .catch(error => {
        // Réactiver le bouton
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-sync-alt"></i> Tester la connexion';
        
        // Afficher l'erreur
        resultDiv.style.display = 'block';
        resultAlert.className = 'alert alert-danger';
        resultMessage.innerHTML = '<i class="fas fa-times-circle"></i> Erreur lors du test de connexion';
    });
}

function handleFormSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const button = document.getElementById('submitBtn');
    const originalHtml = button.innerHTML;
    
    // Désactiver le bouton et ajouter le spinner
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement en cours...';
    
    // Rendre les champs en lecture seule au lieu de les désactiver
    const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], textarea');
    inputs.forEach(input => {
        input.readOnly = true;
        input.style.backgroundColor = '#f5f5f5';
        input.style.cursor = 'not-allowed';
    });
    
    // Désactiver les checkboxes différemment
    const checkboxes = form.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.style.pointerEvents = 'none';
    });
    
    // Désactiver les select et buttons
    const selects = form.querySelectorAll('select');
    selects.forEach(select => {
        select.style.pointerEvents = 'none';
        select.style.backgroundColor = '#f5f5f5';
    });
    
    const buttons = form.querySelectorAll('button:not(#submitBtn)');
    buttons.forEach(btn => btn.disabled = true);
    
    // Soumettre le formulaire
    form.submit();
}

// Fonction pour gérer le switch du type de compte
function updateAccountType() {
    const switchElement = document.getElementById('account_type_switch');
    const hiddenInput = document.getElementById('account_type_hidden');
    const label = document.getElementById('account_type_label');
    
    if (switchElement.checked) {
        hiddenInput.value = 'b2b';
        label.textContent = 'B2B - Professionnel';
    } else {
        hiddenInput.value = 'b2c';
        label.textContent = 'B2C - Grand public';
    }
}

// Vérifier la recommandation MX au chargement
document.addEventListener('DOMContentLoaded', function() {
    checkMxRecommendation();
});

function checkMxRecommendation() {
    const email = '{{ $emailAccount->email }}';
    const currentType = '{{ $emailAccount->account_type }}';
    const badge = document.getElementById('type-recommendation-badge');
    const reason = document.getElementById('type-recommendation-reason');
    const switchElement = document.getElementById('account_type_switch');
    
    // Faire la requête pour obtenir la recommandation
    fetch('{{ route("admin.email-accounts.mx-recommendation") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.recommended_type && data.recommended_type !== currentType) {
            // Afficher le badge de recommandation
            badge.classList.remove('d-none');
            
            // Afficher la raison
            if (data.reason) {
                reason.textContent = '💡 ' + data.reason;
                reason.classList.remove('d-none');
            }
            
            // Faire clignoter le switch pour attirer l'attention
            switchElement.classList.add('border', 'border-warning', 'shadow-sm');
            
            // Animation de pulsation
            let pulseCount = 0;
            const pulseInterval = setInterval(() => {
                switchElement.style.transform = pulseCount % 2 === 0 ? 'scale(1.1)' : 'scale(1)';
                pulseCount++;
                if (pulseCount >= 6) {
                    clearInterval(pulseInterval);
                    switchElement.style.transform = 'scale(1)';
                }
            }, 300);
        }
    })
    .catch(error => {
        console.error('Erreur lors de la vérification MX:', error);
    });
}

// Fonction pour afficher la modal de mot de passe
function showPasswordModal() {
    const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
    modal.show();
}
</script>
@endsection
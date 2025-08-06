@extends('layouts.admin')

@section('title', 'Modifier ' . $provider->display_name)

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.providers.index') }}">Fournisseurs Email</a></li>
            <li class="breadcrumb-item active">{{ $provider->display_name }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Modifier le fournisseur</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.providers.update', $provider) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        {{-- Informations générales --}}
                        <h6 class="text-muted mb-3">Informations générales</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom interne</label>
                                <input type="text" class="form-control" value="{{ $provider->name }}" readonly>
                                <small class="text-muted">Non modifiable</small>
                            </div>
                            <div class="col-md-6">
                                <label for="display_name" class="form-label">Nom d'affichage <span class="text-danger">*</span></label>
                                <input type="text" name="display_name" id="display_name" 
                                    class="form-control @error('display_name') is-invalid @enderror" 
                                    value="{{ old('display_name', $provider->display_name) }}" required>
                                @error('display_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" 
                                class="form-control @error('description') is-invalid @enderror" 
                                rows="2">{{ old('description', $provider->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="provider_type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select name="provider_type" id="provider_type" 
                                    class="form-select @error('provider_type') is-invalid @enderror" required>
                                    <option value="b2c" {{ old('provider_type', $provider->provider_type) == 'b2c' ? 'selected' : '' }}>B2C - Grand public</option>
                                    <option value="b2b" {{ old('provider_type', $provider->provider_type) == 'b2b' ? 'selected' : '' }}>B2B - Professionnel</option>
                                    <option value="custom" {{ old('provider_type', $provider->provider_type) == 'custom' ? 'selected' : '' }}>Personnalisé</option>
                                </select>
                                @error('provider_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Statut</label>
                                <div class="form-check form-switch mt-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                        value="1" {{ old('is_active', $provider->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        Provider actif
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Configuration IMAP --}}
                        <h6 class="text-muted mb-3 mt-4">Configuration IMAP</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label for="imap_host" class="form-label">Serveur IMAP</label>
                                <input type="text" name="imap_host" id="imap_host" 
                                    class="form-control @error('imap_host') is-invalid @enderror" 
                                    value="{{ old('imap_host', $provider->imap_host) }}"
                                    placeholder="imap.example.com">
                                @error('imap_host')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="imap_port" class="form-label">Port</label>
                                <input type="number" name="imap_port" id="imap_port" 
                                    class="form-control @error('imap_port') is-invalid @enderror" 
                                    value="{{ old('imap_port', $provider->imap_port ?: 993) }}">
                                @error('imap_port')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="imap_encryption" class="form-label">Chiffrement IMAP</label>
                                <select name="imap_encryption" id="imap_encryption" 
                                    class="form-select @error('imap_encryption') is-invalid @enderror">
                                    <option value="ssl" {{ old('imap_encryption', $provider->imap_encryption) == 'ssl' ? 'selected' : '' }}>SSL</option>
                                    <option value="tls" {{ old('imap_encryption', $provider->imap_encryption) == 'tls' ? 'selected' : '' }}>TLS</option>
                                    <option value="none" {{ old('imap_encryption', $provider->imap_encryption) == 'none' ? 'selected' : '' }}>Aucun</option>
                                </select>
                                @error('imap_encryption')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input type="hidden" name="validate_cert" value="0">
                                    <input class="form-check-input" type="checkbox" name="validate_cert" id="validate_cert" 
                                        value="1" {{ old('validate_cert', $provider->validate_cert) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="validate_cert">
                                        Valider le certificat SSL
                                    </label>
                                </div>
                            </div>
                        </div>


                        {{-- OAuth --}}
                        <h6 class="text-muted mb-3 mt-4">Authentification OAuth</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="hidden" name="supports_oauth" value="0">
                                    <input class="form-check-input" type="checkbox" name="supports_oauth" id="supports_oauth" 
                                        value="1" {{ old('supports_oauth', $provider->supports_oauth) ? 'checked' : '' }}
                                        onchange="toggleOAuthProvider()">
                                    <label class="form-check-label" for="supports_oauth">
                                        Supporte OAuth 2.0
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="oauth_provider" class="form-label">Provider OAuth</label>
                                <select name="oauth_provider" id="oauth_provider" 
                                    class="form-select @error('oauth_provider') is-invalid @enderror"
                                    {{ !old('supports_oauth', $provider->supports_oauth) ? 'disabled' : '' }}>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="google" {{ old('oauth_provider', $provider->oauth_provider) == 'google' ? 'selected' : '' }}>Google</option>
                                    <option value="microsoft" {{ old('oauth_provider', $provider->oauth_provider) == 'microsoft' ? 'selected' : '' }}>Microsoft</option>
                                </select>
                                @error('oauth_provider')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="hidden" name="requires_app_password" value="0">
                                <input class="form-check-input" type="checkbox" name="requires_app_password" id="requires_app_password" 
                                    value="1" {{ old('requires_app_password', $provider->requires_app_password) ? 'checked' : '' }}>
                                <label class="form-check-label" for="requires_app_password">
                                    Nécessite un mot de passe d'application (2FA)
                                </label>
                            </div>
                        </div>

                        {{-- Détection --}}
                        <h6 class="text-muted mb-3 mt-4">Détection automatique</h6>
                        
                        <div class="mb-3">
                            <label for="domains" class="form-label">Domaines (un par ligne)</label>
                            <textarea name="domains" id="domains" 
                                class="form-control @error('domains') is-invalid @enderror" 
                                rows="3" placeholder="gmail.com&#10;googlemail.com">{{ old('domains', is_array($provider->domains) ? implode("\n", $provider->domains) : $provider->domains) }}</textarea>
                            <small class="text-muted">Domaines email associés à ce provider</small>
                            @error('domains')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="mx_patterns" class="form-label">Patterns MX (un par ligne)</label>
                            <textarea name="mx_patterns" id="mx_patterns" 
                                class="form-control @error('mx_patterns') is-invalid @enderror" 
                                rows="3" placeholder="google.com&#10;googlemail.com">{{ old('mx_patterns', is_array($provider->mx_patterns) ? implode("\n", $provider->mx_patterns) : $provider->mx_patterns) }}</textarea>
                            <small class="text-muted">Patterns pour détecter ce provider via les enregistrements MX</small>
                            @error('mx_patterns')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Rate Limits & Performance --}}
                        <h6 class="text-muted mb-3 mt-4">Limites de connexion</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="max_connections_per_hour" class="form-label">Connexions max/heure</label>
                                <input type="number" name="max_connections_per_hour" id="max_connections_per_hour" 
                                    class="form-control @error('max_connections_per_hour') is-invalid @enderror" 
                                    value="{{ old('max_connections_per_hour', $provider->max_connections_per_hour ?? 60) }}" 
                                    min="1" max="1000">
                                <small class="text-muted">Limite horaire de connexions</small>
                                @error('max_connections_per_hour')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="max_checks_per_connection" class="form-label">Vérifications max/connexion</label>
                                <input type="number" name="max_checks_per_connection" id="max_checks_per_connection" 
                                    class="form-control @error('max_checks_per_connection') is-invalid @enderror" 
                                    value="{{ old('max_checks_per_connection', $provider->max_checks_per_connection ?? 100) }}" 
                                    min="1" max="1000">
                                <small class="text-muted">Emails à vérifier par connexion</small>
                                @error('max_checks_per_connection')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="connection_backoff_minutes" class="form-label">Délai après erreur (min)</label>
                                <input type="number" name="connection_backoff_minutes" id="connection_backoff_minutes" 
                                    class="form-control @error('connection_backoff_minutes') is-invalid @enderror" 
                                    value="{{ old('connection_backoff_minutes', $provider->connection_backoff_minutes ?? 30) }}" 
                                    min="5" max="1440">
                                <small class="text-muted">Temps d'attente après rate limit</small>
                                @error('connection_backoff_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="hidden" name="supports_idle" value="0">
                                <input class="form-check-input" type="checkbox" name="supports_idle" id="supports_idle" 
                                    value="1" {{ old('supports_idle', $provider->supports_idle) ? 'checked' : '' }}>
                                <label class="form-check-label" for="supports_idle">
                                    Supporte IMAP IDLE (connexions persistantes)
                                </label>
                            </div>
                        </div>
                        
                        {{-- Intervalles de vérification progressifs --}}
                        <h6 class="text-muted mb-3">Intervalles de vérification progressifs</h6>
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle"></i> Définissez les intervalles de vérification en fonction de l'âge de l'email. 
                            Par défaut : 0-5min toutes les minutes, 5-15min toutes les 5 minutes, 15-30min toutes les 15 minutes.
                        </div>
                        
                        <div id="check-intervals">
                            @php
                                $intervals = old('check_intervals', $provider->check_intervals ?? [
                                    ['max_age_minutes' => 5, 'interval_minutes' => 1],
                                    ['max_age_minutes' => 15, 'interval_minutes' => 5],
                                    ['max_age_minutes' => 30, 'interval_minutes' => 15]
                                ]);
                            @endphp
                            
                            @foreach($intervals as $index => $interval)
                            <div class="row mb-2 interval-row">
                                <div class="col-md-5">
                                    <input type="number" name="check_intervals[{{ $index }}][max_age_minutes]" 
                                        class="form-control" placeholder="Âge max (min)" 
                                        value="{{ $interval['max_age_minutes'] ?? '' }}" min="1">
                                </div>
                                <div class="col-md-5">
                                    <input type="number" name="check_intervals[{{ $index }}][interval_minutes]" 
                                        class="form-control" placeholder="Intervalle (min)" 
                                        value="{{ $interval['interval_minutes'] ?? '' }}" min="1">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeInterval(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addInterval()">
                            <i class="fas fa-plus"></i> Ajouter un intervalle
                        </button>

                        {{-- Instructions --}}
                        <div class="mb-3 mt-4">
                            <label for="instructions" class="form-label">Instructions de configuration</label>
                            <textarea name="instructions" id="instructions" 
                                class="form-control @error('instructions') is-invalid @enderror" 
                                rows="3">{{ old('instructions', $provider->instructions) }}</textarea>
                            <small class="text-muted">Instructions pour aider les utilisateurs à configurer leur compte</small>
                            @error('instructions')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes internes</label>
                            <textarea name="notes" id="notes" 
                                class="form-control @error('notes') is-invalid @enderror" 
                                rows="2">{{ old('notes', $provider->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Boutons --}}
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer
                            </button>
                            <a href="{{ route('admin.providers.index') }}" class="btn btn-outline-secondary">
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Colonne latérale --}}
        <div class="col-lg-4">
            {{-- Test de connexion --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Test de connexion</h5>
                </div>
                <div class="card-body">
                    @if($provider->imap_host)
                        <p class="text-muted small">Teste la connexion au serveur IMAP configuré.</p>
                        <button class="btn btn-primary w-100" onclick="testConnection()">
                            <i class="fas fa-plug"></i> Tester la connexion
                        </button>
                        <div id="test-result" class="mt-3" style="display: none;">
                            <div class="alert" role="alert">
                                <span id="test-message"></span>
                            </div>
                        </div>
                    @else
                        <p class="text-muted">Configurez d'abord le serveur IMAP pour pouvoir tester la connexion.</p>
                    @endif
                </div>
            </div>

            {{-- Informations --}}
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informations</h5>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Créé le</dt>
                        <dd>{{ $provider->created_at->format('d/m/Y H:i') }}</dd>
                        
                        <dt>Dernière modification</dt>
                        <dd>{{ $provider->updated_at->format('d/m/Y H:i') }}</dd>
                        
                        <dt>Comptes associés</dt>
                        <dd>
                            @php
                                $accountCount = $provider->emailAccounts()->count();
                            @endphp
                            {{ $accountCount }} compte(s)
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleOAuthProvider() {
    const checkbox = document.getElementById('supports_oauth');
    const select = document.getElementById('oauth_provider');
    select.disabled = !checkbox.checked;
    if (!checkbox.checked) {
        select.value = '';
    }
}

let intervalIndex = {{ count($intervals) }};

function addInterval() {
    const container = document.getElementById('check-intervals');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2 interval-row';
    newRow.innerHTML = `
        <div class="col-md-5">
            <input type="number" name="check_intervals[${intervalIndex}][max_age_minutes]" 
                class="form-control" placeholder="Âge max (min)" min="1">
        </div>
        <div class="col-md-5">
            <input type="number" name="check_intervals[${intervalIndex}][interval_minutes]" 
                class="form-control" placeholder="Intervalle (min)" min="1">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeInterval(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
    intervalIndex++;
}

function removeInterval(button) {
    button.closest('.interval-row').remove();
}

function testConnection() {
    const button = event.target;
    const resultDiv = document.getElementById('test-result');
    const alertDiv = resultDiv.querySelector('.alert');
    const messageSpan = document.getElementById('test-message');
    
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Test en cours...';
    
    fetch('{{ route("admin.providers.test", $provider) }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-plug"></i> Tester la connexion';
        
        resultDiv.style.display = 'block';
        
        if (data.success) {
            alertDiv.className = 'alert alert-success';
            messageSpan.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
        } else {
            alertDiv.className = 'alert alert-danger';
            messageSpan.innerHTML = '<i class="fas fa-times-circle"></i> ' + data.message;
        }
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-plug"></i> Tester la connexion';
        
        resultDiv.style.display = 'block';
        alertDiv.className = 'alert alert-danger';
        messageSpan.innerHTML = '<i class="fas fa-times-circle"></i> Erreur lors du test';
    });
}
</script>
@endsection
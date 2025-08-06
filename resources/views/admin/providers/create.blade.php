@extends('layouts.admin')

@section('title', 'Nouveau fournisseur')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.providers.index') }}">Fournisseurs Email</a></li>
            <li class="breadcrumb-item active">Nouveau fournisseur</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Créer un nouveau fournisseur</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.providers.store') }}" method="POST">
                        @csrf
                        
                        {{-- Informations générales --}}
                        <h6 class="text-muted mb-3">Informations générales</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Nom interne <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" 
                                    class="form-control @error('name') is-invalid @enderror" 
                                    value="{{ old('name') }}" required>
                                <small class="text-muted">Nom unique sans espaces (ex: gmail, outlook)</small>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="display_name" class="form-label">Nom d'affichage <span class="text-danger">*</span></label>
                                <input type="text" name="display_name" id="display_name" 
                                    class="form-control @error('display_name') is-invalid @enderror" 
                                    value="{{ old('display_name') }}" required>
                                @error('display_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" 
                                class="form-control @error('description') is-invalid @enderror" 
                                rows="2">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="provider_type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select name="provider_type" id="provider_type" 
                                    class="form-select @error('provider_type') is-invalid @enderror" required>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="b2c" {{ old('provider_type') == 'b2c' ? 'selected' : '' }}>B2C - Grand public</option>
                                    <option value="b2b" {{ old('provider_type') == 'b2b' ? 'selected' : '' }}>B2B - Professionnel</option>
                                    <option value="custom" {{ old('provider_type') == 'custom' ? 'selected' : '' }}>Personnalisé</option>
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
                                        value="1" {{ old('is_active', true) ? 'checked' : '' }}>
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
                                    value="{{ old('imap_host') }}"
                                    placeholder="imap.example.com">
                                @error('imap_host')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="imap_port" class="form-label">Port</label>
                                <input type="number" name="imap_port" id="imap_port" 
                                    class="form-control @error('imap_port') is-invalid @enderror" 
                                    value="{{ old('imap_port', 993) }}">
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
                                    <option value="ssl" {{ old('imap_encryption', 'ssl') == 'ssl' ? 'selected' : '' }}>SSL</option>
                                    <option value="tls" {{ old('imap_encryption') == 'tls' ? 'selected' : '' }}>TLS</option>
                                    <option value="none" {{ old('imap_encryption') == 'none' ? 'selected' : '' }}>Aucun</option>
                                </select>
                                @error('imap_encryption')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input type="hidden" name="validate_cert" value="0">
                                    <input class="form-check-input" type="checkbox" name="validate_cert" id="validate_cert" 
                                        value="1" {{ old('validate_cert', true) ? 'checked' : '' }}>
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
                                        value="1" {{ old('supports_oauth') ? 'checked' : '' }}
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
                                    {{ !old('supports_oauth') ? 'disabled' : '' }}>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="google" {{ old('oauth_provider') == 'google' ? 'selected' : '' }}>Google</option>
                                    <option value="microsoft" {{ old('oauth_provider') == 'microsoft' ? 'selected' : '' }}>Microsoft</option>
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
                                    value="1" {{ old('requires_app_password') ? 'checked' : '' }}>
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
                                rows="3" placeholder="gmail.com&#10;googlemail.com">{{ old('domains') }}</textarea>
                            <small class="text-muted">Domaines email associés à ce provider</small>
                            @error('domains')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="mx_patterns" class="form-label">Patterns MX (un par ligne)</label>
                            <textarea name="mx_patterns" id="mx_patterns" 
                                class="form-control @error('mx_patterns') is-invalid @enderror" 
                                rows="3" placeholder="google.com&#10;googlemail.com">{{ old('mx_patterns') }}</textarea>
                            <small class="text-muted">Patterns pour détecter ce provider via les enregistrements MX</small>
                            @error('mx_patterns')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Instructions --}}
                        <div class="mb-3">
                            <label for="instructions" class="form-label">Instructions de configuration</label>
                            <textarea name="instructions" id="instructions" 
                                class="form-control @error('instructions') is-invalid @enderror" 
                                rows="3">{{ old('instructions') }}</textarea>
                            <small class="text-muted">Instructions pour aider les utilisateurs à configurer leur compte</small>
                            @error('instructions')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes internes</label>
                            <textarea name="notes" id="notes" 
                                class="form-control @error('notes') is-invalid @enderror" 
                                rows="2">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Boutons --}}
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Créer le fournisseur
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
            {{-- Aide --}}
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Aide</h5>
                </div>
                <div class="card-body">
                    <h6>Types de fournisseurs</h6>
                    <ul class="small">
                        <li><strong>B2C</strong> : Fournisseurs grand public (Gmail, Yahoo, etc.)</li>
                        <li><strong>B2B</strong> : Fournisseurs professionnels (Exchange, G Suite, etc.)</li>
                        <li><strong>Personnalisé</strong> : Serveurs mail personnalisés</li>
                    </ul>
                    
                    <h6 class="mt-3">Configuration IMAP/SMTP</h6>
                    <p class="small text-muted">
                        Les paramètres IMAP et SMTP sont optionnels mais nécessaires pour tester les connexions 
                        et permettre aux utilisateurs de configurer leurs comptes automatiquement.
                    </p>
                    
                    <h6 class="mt-3">OAuth</h6>
                    <p class="small text-muted">
                        Si le fournisseur supporte OAuth, sélectionnez le bon provider (Google ou Microsoft). 
                        Cela permettra une authentification plus sécurisée sans mot de passe.
                    </p>
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
</script>
@endsection
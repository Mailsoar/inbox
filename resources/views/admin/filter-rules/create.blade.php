@extends('layouts.admin')

@section('title', 'Nouvelle règle de filtrage')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Nouvelle règle de filtrage</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.filter-rules.store') }}">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="type" class="form-label">Type de règle</label>
                            <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required onchange="updateFormFields()">
                                <option value="">Sélectionnez un type</option>
                                <option value="ip" {{ old('type') == 'ip' ? 'selected' : '' }}>Adresse IP</option>
                                <option value="domain" {{ old('type') == 'domain' ? 'selected' : '' }}>Domaine</option>
                                <option value="mx" {{ old('type') == 'mx' ? 'selected' : '' }}>Serveur MX</option>
                                <option value="email_pattern" {{ old('type') == 'email_pattern' ? 'selected' : '' }}>Pattern Email</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="value" class="form-label">Valeur</label>
                            <input type="text" name="value" id="value" class="form-control @error('value') is-invalid @enderror" value="{{ old('value') }}" required>
                            <div class="form-text" id="value-help">
                                <!-- Le texte d'aide sera mis à jour par JavaScript -->
                            </div>
                            @error('value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="action" class="form-label">Action</label>
                            <select name="action" id="action" class="form-select @error('action') is-invalid @enderror" required>
                                <option value="block" {{ old('action') == 'block' ? 'selected' : '' }}>Bloquer</option>
                                <option value="allow" {{ old('action') == 'allow' ? 'selected' : '' }}>Autoriser</option>
                            </select>
                            @error('action')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" rows="2">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label for="is_active" class="form-check-label">Règle active</label>
                            </div>
                        </div>

                        <!-- Options spéciales pour la normalisation des emails -->
                        <div id="normalization-options" style="display: none;">
                            <h5 class="mb-3">Options de normalisation</h5>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="normalize_gmail_dots" id="normalize_gmail_dots" class="form-check-input" value="1" {{ old('normalize_gmail_dots', true) ? 'checked' : '' }}>
                                    <label for="normalize_gmail_dots" class="form-check-label">
                                        Normaliser les points dans Gmail
                                        <small class="text-muted d-block">Traite user.name@gmail.com comme username@gmail.com</small>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="normalize_plus_aliases" id="normalize_plus_aliases" class="form-check-input" value="1" {{ old('normalize_plus_aliases', true) ? 'checked' : '' }}>
                                    <label for="normalize_plus_aliases" class="form-check-label">
                                        Normaliser les alias avec +
                                        <small class="text-muted d-block">Traite user+alias@domain.com comme user@domain.com</small>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="gmail_domains" class="form-label">Domaines Gmail</label>
                                <input type="text" name="gmail_domains" id="gmail_domains" class="form-control" value="{{ old('gmail_domains', 'gmail.com,googlemail.com') }}">
                                <small class="form-text">Séparez les domaines par des virgules</small>
                            </div>

                            <div class="mb-3">
                                <label for="outlook_domains" class="form-label">Domaines Outlook</label>
                                <input type="text" name="outlook_domains" id="outlook_domains" class="form-control" value="{{ old('outlook_domains', 'outlook.com,hotmail.com,live.com,msn.com') }}">
                                <small class="form-text">Séparez les domaines par des virgules</small>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.filter-rules.index') }}" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">Créer la règle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Aide</h5>
                </div>
                <div class="card-body">
                    <h6>Types de règles</h6>
                    <ul class="small">
                        <li><strong>IP :</strong> Bloquer/autoriser des adresses IP spécifiques ou des plages</li>
                        <li><strong>Domaine :</strong> Bloquer/autoriser des domaines d'email</li>
                        <li><strong>MX :</strong> Bloquer/autoriser basé sur les serveurs MX</li>
                        <li><strong>Pattern Email :</strong> Règles spéciales pour la normalisation</li>
                    </ul>

                    <h6 class="mt-3">Exemples de valeurs</h6>
                    <ul class="small">
                        <li>IP simple : <code>192.168.1.1</code></li>
                        <li>IP avec wildcard : <code>192.168.*.*</code></li>
                        <li>Domaine : <code>example.com</code></li>
                        <li>Sous-domaines : <code>*.example.com</code></li>
                    </ul>

                    <h6 class="mt-3">Priorité des règles</h6>
                    <p class="small">Les règles "Autoriser" ont priorité sur les règles "Bloquer" pour la même valeur.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateFormFields() {
    const type = document.getElementById('type').value;
    const valueHelp = document.getElementById('value-help');
    const normOptions = document.getElementById('normalization-options');
    const valueField = document.getElementById('value');
    
    // Réinitialiser
    normOptions.style.display = 'none';
    valueField.value = '';
    
    switch(type) {
        case 'ip':
            valueHelp.textContent = 'Exemples: 192.168.1.1, 192.168.*.*, 10.0.0.0/24';
            break;
        case 'domain':
            valueHelp.textContent = 'Exemples: gmail.com, *.company.com';
            break;
        case 'mx':
            valueHelp.textContent = 'Exemple: mx.google.com';
            break;
        case 'email_pattern':
            valueHelp.textContent = 'Utilisez "normalization_settings" pour les options de normalisation';
            valueField.value = 'normalization_settings';
            normOptions.style.display = 'block';
            break;
        default:
            valueHelp.textContent = '';
    }
}

// Initialiser au chargement
document.addEventListener('DOMContentLoaded', updateFormFields);
</script>
@endsection
@extends('layouts.admin')

@section('page-title', 'Ajouter un compte email')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.email-accounts.index') }}">Comptes Email</a></li>
            <li class="breadcrumb-item active" aria-current="page">Ajouter un compte</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Ajouter un compte email</h1>
    </div>


    {{-- Provider Selection --}}
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Choisissez le type de compte</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        {{-- Gmail --}}
                        <div class="col-md-6">
                            <div class="card h-100 provider-card">
                                <div class="card-body text-center">
                                    <i class="fab fa-google text-danger mb-3" style="font-size: 3rem;"></i>
                                    <h5 class="card-title">Gmail</h5>
                                    <p class="card-text text-muted">
                                        Connexion OAuth2 sécurisée avec votre compte Gmail
                                    </p>
                                    <a href="{{ route('oauth.gmail.connect') }}" class="btn btn-danger">
                                        <i class="fab fa-google"></i> Se connecter avec Gmail
                                    </a>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle"></i> L'email sera récupéré automatiquement
                                    </small>
                                </div>
                            </div>
                        </div>

                        {{-- Microsoft/Outlook --}}
                        <div class="col-md-6">
                            <div class="card h-100 provider-card">
                                <div class="card-body text-center">
                                    <i class="fab fa-microsoft text-primary mb-3" style="font-size: 3rem;"></i>
                                    <h5 class="card-title">Microsoft / Outlook</h5>
                                    <p class="card-text text-muted">
                                        Connexion OAuth2 avec Outlook, Hotmail ou Office 365
                                    </p>
                                    <a href="{{ route('oauth.microsoft.connect') }}" class="btn btn-primary">
                                        <i class="fab fa-microsoft"></i> Se connecter avec Microsoft
                                    </a>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle"></i> Vous pourrez choisir le compte à connecter
                                    </small>
                                </div>
                            </div>
                        </div>

                        {{-- Yahoo --}}
                        <div class="col-md-6">
                            <div class="card h-100 provider-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-envelope text-purple mb-3" style="font-size: 3rem;"></i>
                                    <h5 class="card-title">Yahoo Mail</h5>
                                    <p class="card-text text-muted">
                                        Connexion avec mot de passe d'application Yahoo
                                    </p>
                                    <button type="button" class="btn btn-purple text-white" data-bs-toggle="modal" data-bs-target="#yahooModal">
                                        <i class="fas fa-key"></i> Configurer Yahoo
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Generic IMAP --}}
                        <div class="col-md-6">
                            <div class="card h-100 provider-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-server text-secondary mb-3" style="font-size: 3rem;"></i>
                                    <h5 class="card-title">IMAP Générique</h5>
                                    <p class="card-text text-muted">
                                        Pour tout autre serveur email compatible IMAP
                                    </p>
                                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#imapModal">
                                        <i class="fas fa-cog"></i> Configurer IMAP
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Note :</strong> Après l'authentification, vous serez redirigé vers la page de configuration pour finaliser l'ajout du compte.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Yahoo --}}
<div class="modal fade" id="yahooModal" tabindex="-1" aria-labelledby="yahooModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('admin.email-accounts.store') }}" method="POST" id="yahooForm" onsubmit="handleFormSubmit(event, 'yahooSubmitBtn')">
                @csrf
                <input type="hidden" name="provider" value="yahoo">
                <input type="hidden" name="_debug" value="1">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="yahooModalLabel">
                        <i class="fas fa-envelope text-purple"></i> Configuration Yahoo Mail
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Important :</strong> Yahoo nécessite un mot de passe d'application. 
                        <a href="https://help.yahoo.com/kb/SLN15241.html" target="_blank">
                            Comment créer un mot de passe d'application Yahoo
                        </a>
                    </div>

                    <div class="mb-3">
                        <label for="yahoo_email" class="form-label">Adresse email Yahoo</label>
                        <input type="email" 
                               class="form-control" 
                               id="yahoo_email" 
                               name="email" 
                               placeholder="exemple@yahoo.com"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="yahoo_password" class="form-label">Mot de passe d'application</label>
                        <input type="password" 
                               class="form-control" 
                               id="yahoo_password" 
                               name="password" 
                               placeholder="Mot de passe d'application (16 caractères)"
                               required>
                        <small class="form-text text-muted">
                            Utilisez un mot de passe d'application, pas votre mot de passe Yahoo normal
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="yahoo_name" class="form-label">Nom du compte (optionnel)</label>
                        <input type="text" 
                               class="form-control" 
                               id="yahoo_name" 
                               name="name" 
                               placeholder="Mon compte Yahoo">
                    </div>
                    
                    <div class="mb-3">
                        <label for="yahoo_account_type" class="form-label">
                            Type de compte
                            <span class="badge bg-info text-white ms-1" id="yahoo-type-badge" style="display: none;">
                                <i class="fas fa-magic"></i> Recommandé
                            </span>
                        </label>
                        <select class="form-select" id="yahoo_account_type" name="account_type" required>
                            <option value="b2c" selected>B2C - Grand public</option>
                            <option value="b2b">B2B - Professionnel</option>
                        </select>
                        <small class="form-text text-muted" id="yahoo-type-reason">
                            Yahoo est généralement un service B2C (grand public)
                        </small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="yahooSubmitBtn">
                        <i class="fas fa-save"></i> Ajouter le compte
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal IMAP Générique --}}
<div class="modal fade" id="imapModal" tabindex="-1" aria-labelledby="imapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('admin.email-accounts.store') }}" method="POST" id="imapForm" onsubmit="handleFormSubmit(event, 'imapSubmitBtn')">
                @csrf
                <input type="hidden" name="provider" value="imap">
                <input type="hidden" name="_debug" value="1">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="imapModalLabel">
                        <i class="fas fa-server"></i> Configuration IMAP
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Informations du compte</h6>
                            
                            <div class="mb-3">
                                <label for="imap_provider" class="form-label">
                                    Fournisseur 
                                    <span class="badge bg-info text-white ms-1" id="auto-config-badge" style="display: none;">
                                        <i class="fas fa-magic"></i> Auto-configuré
                                    </span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text" id="provider-logo" style="display: none;">
                                        <img id="provider-logo-img" src="" alt="" style="width: 20px; height: 20px; object-fit: contain;" onerror="this.src='/images/providers/generic.svg'">
                                    </span>
                                    <select class="form-select" id="imap_provider" name="imap_provider_id" onchange="updateImapSettings()">
                                        <option value="">Choisissez un fournisseur...</option>
                                        <!-- Options will be populated by JavaScript -->
                                    </select>
                                </div>
                                <small class="form-text text-muted">
                                    Sélectionnez votre fournisseur pour une configuration automatique
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="imap_email" class="form-label">Adresse email</label>
                                <input type="email" 
                                       class="form-control" 
                                       id="imap_email" 
                                       name="email" 
                                       onchange="suggestProvider()"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="imap_password" class="form-label">Mot de passe</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="imap_password" 
                                       name="password" 
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="imap_name" class="form-label">Nom du compte (optionnel)</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="imap_name" 
                                       name="name">
                            </div>
                            
                            <div class="mb-3">
                                <label for="imap_account_type" class="form-label">
                                    Type de compte
                                    <span class="badge bg-info text-white ms-1" id="imap-type-badge" style="display: none;">
                                        <i class="fas fa-magic"></i> Recommandé
                                    </span>
                                </label>
                                <select class="form-select" id="imap_account_type" name="account_type" required>
                                    <option value="b2c">B2C - Grand public</option>
                                    <option value="b2b" selected>B2B - Professionnel</option>
                                </select>
                                <small class="form-text text-muted" id="imap-type-reason">
                                    <!-- Raison sera mise à jour par JavaScript -->
                                </small>
                                <div id="mx-loading" class="mt-2" style="display: none;">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Analyse MX...</span>
                                    </div>
                                    <small class="text-muted ms-2">Analyse des enregistrements MX...</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6>Configuration IMAP</h6>
                            
                            <div class="mb-3">
                                <label for="imap_host" class="form-label">
                                    Serveur IMAP 
                                    <span class="badge bg-success text-white ms-1" id="host-auto-badge" style="display: none;">
                                        <i class="fas fa-check"></i> Auto
                                    </span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="imap_host" 
                                       name="imap_host" 
                                       placeholder="imap.example.com"
                                       required>
                                <small class="form-text" id="provider-hint" style="display: none;">
                                    <i class="fas fa-info-circle text-success"></i>
                                    <span class="text-success">Configuration automatique basée sur le fournisseur sélectionné</span>
                                </small>
                            </div>

                            <div class="mb-3">
                                <label for="imap_port" class="form-label">Port</label>
                                <select class="form-select" id="imap_port" name="imap_port" required>
                                    <option value="993" selected>993 (SSL/TLS)</option>
                                    <option value="143">143 (STARTTLS)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="imap_encryption" class="form-label">Chiffrement</label>
                                <select class="form-select" id="imap_encryption" name="imap_encryption" required>
                                    <option value="ssl" selected>SSL/TLS</option>
                                    <option value="tls">STARTTLS</option>
                                    <option value="none">Aucun</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Astuce :</strong> Sélectionnez votre fournisseur pour configurer automatiquement les paramètres IMAP.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="imapSubmitBtn">
                        <i class="fas fa-save"></i> Ajouter le compte
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
.btn-purple {
    background-color: #6f42c1;
    border-color: #6f42c1;
}

.btn-purple:hover {
    background-color: #5a32a3;
    border-color: #5a32a3;
}

.provider-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.provider-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.text-purple {
    color: #6f42c1;
}
</style>
@endpush

@push('scripts')
<script>
// IMAP Providers data - will be populated from backend
let imapProviders = [];

// Load IMAP providers when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadImapProviders();
});

function loadImapProviders() {
    // Load providers from backend data
    @if(isset($imapProviders))
        imapProviders = {!! $imapProviders->toJson() !!};
    @else
        imapProviders = [];
    @endif
    
    populateProviderSelect();
}

function populateProviderSelect() {
    const select = document.getElementById('imap_provider');
    if (!select) return;
    
    // Clear existing options except the first one
    while (select.children.length > 1) {
        select.removeChild(select.lastChild);
    }
    
    // Add provider options
    imapProviders.forEach(provider => {
        const option = document.createElement('option');
        option.value = provider.id;
        option.textContent = provider.display_name;
        option.dataset.provider = JSON.stringify(provider);
        select.appendChild(option);
    });
}

function updateImapSettings() {
    const select = document.getElementById('imap_provider');
    const selectedOption = select.options[select.selectedIndex];
    const providerLogo = document.getElementById('provider-logo');
    const providerLogoImg = document.getElementById('provider-logo-img');
    const autoConfigBadge = document.getElementById('auto-config-badge');
    const hostAutoBadge = document.getElementById('host-auto-badge');
    const providerHint = document.getElementById('provider-hint');
    
    if (!selectedOption || !selectedOption.dataset.provider) {
        // Reset everything if no provider selected
        document.getElementById('imap_host').value = '';
        document.getElementById('imap_port').value = '993';
        document.getElementById('imap_encryption').value = 'ssl';
        providerHint.style.display = 'none';
        providerLogo.style.display = 'none';
        autoConfigBadge.style.display = 'none';
        hostAutoBadge.style.display = 'none';
        return;
    }
    
    const provider = JSON.parse(selectedOption.dataset.provider);
    
    // Show provider logo (always show, will fallback to generic if needed)
    providerLogoImg.src = `/images/providers/${provider.name}.svg`;
    providerLogoImg.alt = provider.display_name;
    providerLogoImg.onerror = function() {
        this.src = '/images/providers/generic.svg';
    };
    providerLogo.style.display = 'block';
    
    // Only auto-fill if it's not custom configuration
    if (provider.name !== 'custom') {
        document.getElementById('imap_host').value = provider.imap_host;
        document.getElementById('imap_port').value = provider.imap_port;
        document.getElementById('imap_encryption').value = provider.encryption;
        
        // Show auto-configuration indicators
        autoConfigBadge.style.display = 'inline';
        hostAutoBadge.style.display = 'inline';
        providerHint.style.display = 'block';
        
        // Add visual feedback with animation
        autoConfigBadge.classList.add('animate__animated', 'animate__pulse');
        setTimeout(() => {
            autoConfigBadge.classList.remove('animate__animated', 'animate__pulse');
        }, 1000);
    } else {
        document.getElementById('imap_host').value = '';
        autoConfigBadge.style.display = 'none';
        hostAutoBadge.style.display = 'none';
        providerHint.style.display = 'none';
    }
}

function suggestProvider() {
    const emailInput = document.getElementById('imap_email');
    const providerSelect = document.getElementById('imap_provider');
    
    if (!emailInput.value || !emailInput.value.includes('@')) {
        return;
    }
    
    const domain = emailInput.value.split('@')[1].toLowerCase();
    
    // Find provider that supports this domain
    const suggestedProvider = imapProviders.find(provider => 
        provider.common_domains && provider.common_domains.includes(domain)
    );
    
    if (suggestedProvider) {
        providerSelect.value = suggestedProvider.id;
        updateImapSettings();
        
        // Show success notification
        showAutoDetectionAlert(suggestedProvider.display_name, domain);
    }
    
    // Analyze MX records for account type recommendation
    analyzeMxRecords(emailInput.value);
}

function showAutoDetectionAlert(providerName, domain) {
    // Create a temporary alert
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show mt-2';
    alertDiv.innerHTML = `
        <i class="fas fa-magic"></i>
        <strong>Détection automatique !</strong> 
        Fournisseur "${providerName}" détecté pour le domaine "${domain}".
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert after the email input
    const emailInput = document.getElementById('imap_email');
    emailInput.parentNode.insertBefore(alertDiv, emailInput.nextSibling);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function handleFormSubmit(event, buttonId) {
    event.preventDefault();
    
    const form = event.target;
    const button = document.getElementById(buttonId);
    const originalHtml = button.innerHTML;
    
    // Désactiver le bouton et ajouter le spinner
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion en cours...';
    
    // Rendre les champs en lecture seule au lieu de les désactiver
    // (les champs disabled ne sont pas envoyés avec le formulaire)
    const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], textarea');
    inputs.forEach(input => {
        input.readOnly = true;
        input.style.backgroundColor = '#f5f5f5';
        input.style.cursor = 'not-allowed';
    });
    
    // Désactiver les select et buttons
    const selects = form.querySelectorAll('select');
    selects.forEach(select => {
        select.style.pointerEvents = 'none';
        select.style.backgroundColor = '#f5f5f5';
    });
    
    const buttons = form.querySelectorAll('button:not(#' + buttonId + ')');
    buttons.forEach(btn => btn.disabled = true);
    
    // Soumettre le formulaire
    form.submit();
}

// Analyser les enregistrements MX
function analyzeMxRecords(email) {
    const mxLoading = document.getElementById('mx-loading');
    const typeSelect = document.getElementById('imap_account_type');
    const typeBadge = document.getElementById('imap-type-badge');
    const typeReason = document.getElementById('imap-type-reason');
    
    // Show loading
    mxLoading.style.display = 'block';
    typeBadge.style.display = 'none';
    typeReason.textContent = '';
    
    // Make AJAX request to get MX recommendation
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
        // Hide loading
        mxLoading.style.display = 'none';
        
        if (data.recommended_type) {
            // Update account type based on recommendation
            typeSelect.value = data.recommended_type;
            
            // Show badge and reason
            typeBadge.style.display = 'inline';
            typeReason.textContent = data.reason || '';
            
            // Add confidence indicator
            if (data.confidence === 'high') {
                typeBadge.className = 'badge bg-success text-white ms-1';
                typeBadge.innerHTML = '<i class="fas fa-check-circle"></i> Recommandé';
            } else if (data.confidence === 'medium') {
                typeBadge.className = 'badge bg-info text-white ms-1';
                typeBadge.innerHTML = '<i class="fas fa-info-circle"></i> Suggéré';
            } else {
                typeBadge.className = 'badge bg-warning text-dark ms-1';
                typeBadge.innerHTML = '<i class="fas fa-question-circle"></i> Incertain';
            }
            
            // Handle detected antispam systems
            if (data.detected_systems && data.detected_systems.length > 0) {
                const systemNames = data.detected_systems.map(s => s.display_name).join(', ');
                typeReason.textContent += ` (Filtres détectés : ${systemNames})`;
            }
        }
    })
    .catch(error => {
        console.error('Error analyzing MX records:', error);
        mxLoading.style.display = 'none';
    });
}

// Also analyze Yahoo emails
document.getElementById('yahoo_email').addEventListener('change', function() {
    if (this.value && this.value.includes('@')) {
        // For Yahoo, we know it's B2C but let's still check for any surprises
        analyzeMxRecordsForYahoo(this.value);
    }
});

function analyzeMxRecordsForYahoo(email) {
    // Similar to analyzeMxRecords but for Yahoo modal
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
        const typeSelect = document.getElementById('yahoo_account_type');
        const typeBadge = document.getElementById('yahoo-type-badge');
        const typeReason = document.getElementById('yahoo-type-reason');
        
        if (data.recommended_type) {
            typeSelect.value = data.recommended_type;
            
            if (data.recommended_type !== 'b2c' || data.confidence !== 'high') {
                // Show badge only if it's not the expected B2C
                typeBadge.style.display = 'inline';
                typeReason.textContent = data.reason || '';
            }
        }
    })
    .catch(error => {
        console.error('Error analyzing MX records:', error);
    });
}
</script>
@endpush

@endsection
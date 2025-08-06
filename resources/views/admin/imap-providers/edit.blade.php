@extends('layouts.admin')

@section('page-title', 'Modifier le fournisseur IMAP')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.imap-providers.index') }}">Fournisseurs IMAP</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $imapProvider->display_name }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <img src="{{ $imapProvider->getLogoUrl() }}" 
                     alt="{{ $imapProvider->display_name }}" 
                     class="me-2"
                     style="width: 32px; height: 32px; object-fit: contain;"
                     onerror="this.src='/images/providers/generic.png'">
                Modifier {{ $imapProvider->display_name }}
                @if($imapProvider->isProtected())
                    <span class="badge bg-info ms-2" title="Fournisseur par défaut">
                        <i class="fas fa-shield-alt"></i> Protégé
                    </span>
                @endif
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informations du fournisseur</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.imap-providers.update', $imapProvider) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nom technique <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name', $imapProvider->name) }}"
                                           placeholder="ex: example_mail"
                                           {{ $imapProvider->isProtected() ? 'readonly' : '' }}
                                           required>
                                    <small class="form-text text-muted">
                                        @if($imapProvider->isProtected())
                                            Le nom technique des fournisseurs par défaut ne peut pas être modifié
                                        @else
                                            Identifiant unique (lettres, chiffres, tirets et underscores uniquement)
                                        @endif
                                    </small>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="display_name" class="form-label">Nom d'affichage <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('display_name') is-invalid @enderror" 
                                           id="display_name" 
                                           name="display_name" 
                                           value="{{ old('display_name', $imapProvider->display_name) }}"
                                           placeholder="ex: Example Mail"
                                           required>
                                    @error('display_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description" 
                                      rows="3"
                                      placeholder="Description du fournisseur email">{{ old('description', $imapProvider->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Logo du fournisseur</label>
                            <div class="d-flex align-items-center mb-2">
                                <img src="{{ $imapProvider->getLogoUrl() }}" 
                                     alt="{{ $imapProvider->display_name }}" 
                                     style="width: 48px; height: 48px; object-fit: contain;"
                                     class="border rounded me-3"
                                     onerror="this.src='/images/providers/generic.svg'">
                                <div>
                                    @if($imapProvider->hasCustomLogo())
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Logo personnalisé disponible
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-image"></i> Logo générique
                                        </span>
                                    @endif
                                    <small class="d-block text-muted mt-1">
                                        Fichiers attendus : <code>/images/providers/{{ $imapProvider->name }}.svg</code> ou <code>.png</code>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="mb-3">Configuration IMAP</h6>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="imap_host" class="form-label">Serveur IMAP <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('imap_host') is-invalid @enderror" 
                                           id="imap_host" 
                                           name="imap_host" 
                                           value="{{ old('imap_host', $imapProvider->imap_host) }}"
                                           placeholder="imap.example.com"
                                           required>
                                    @error('imap_host')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="imap_port" class="form-label">Port <span class="text-danger">*</span></label>
                                    <select class="form-select @error('imap_port') is-invalid @enderror" 
                                            id="imap_port" 
                                            name="imap_port" 
                                            required>
                                        <option value="993" {{ old('imap_port', $imapProvider->imap_port) == '993' ? 'selected' : '' }}>993 (SSL/TLS)</option>
                                        <option value="143" {{ old('imap_port', $imapProvider->imap_port) == '143' ? 'selected' : '' }}>143 (STARTTLS)</option>
                                        <option value="110" {{ old('imap_port', $imapProvider->imap_port) == '110' ? 'selected' : '' }}>110 (POP3)</option>
                                    </select>
                                    @error('imap_port')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="encryption" class="form-label">Chiffrement <span class="text-danger">*</span></label>
                                    <select class="form-select @error('encryption') is-invalid @enderror" 
                                            id="encryption" 
                                            name="encryption" 
                                            required>
                                        <option value="ssl" {{ old('encryption', $imapProvider->encryption) == 'ssl' ? 'selected' : '' }}>SSL/TLS</option>
                                        <option value="tls" {{ old('encryption', $imapProvider->encryption) == 'tls' ? 'selected' : '' }}>STARTTLS</option>
                                        <option value="none" {{ old('encryption', $imapProvider->encryption) == 'none' ? 'selected' : '' }}>Aucun</option>
                                    </select>
                                    @error('encryption')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Options</label>
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="validate_cert" 
                                               name="validate_cert" 
                                               value="1"
                                               {{ old('validate_cert', $imapProvider->validate_cert) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="validate_cert">
                                            Valider le certificat SSL
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="is_active" 
                                               name="is_active" 
                                               value="1"
                                               {{ old('is_active', $imapProvider->is_active) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            Fournisseur actif
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="mb-3">Domaines associés</h6>
                        
                        <div class="mb-3">
                            <label for="common_domains" class="form-label">Domaines email</label>
                            <input type="text" 
                                   class="form-control @error('common_domains') is-invalid @enderror" 
                                   id="common_domains" 
                                   name="common_domains" 
                                   value="{{ old('common_domains', implode(', ', $imapProvider->common_domains ?? [])) }}"
                                   placeholder="example.com, mail.example.com">
                            <small class="form-text text-muted">
                                Liste des domaines séparés par des virgules (utilisés pour la détection automatique)
                            </small>
                            @error('common_domains')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.imap-providers.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                            <div>
                                <button type="button" class="btn btn-info me-2" onclick="testConfiguration()">
                                    <i class="fas fa-vials"></i> Tester la config
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Enregistrer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-chart-bar"></i> Statistiques
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h5 class="text-primary">{{ $imapProvider->emailAccounts()->count() }}</h5>
                            <small class="text-muted">Comptes associés</small>
                        </div>
                        <div class="col-6">
                            <h5 class="text-success">{{ $imapProvider->emailAccounts()->where('is_active', true)->count() }}</h5>
                            <small class="text-muted">Comptes actifs</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i> Informations
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small">
                        <strong>Créé le :</strong><br>
                        {{ $imapProvider->created_at->format('d/m/Y H:i') }}
                    </p>
                    <p class="small">
                        <strong>Dernière modification :</strong><br>
                        {{ $imapProvider->updated_at->format('d/m/Y H:i') }}
                    </p>
                    
                    @if($imapProvider->isProtected())
                        <div class="alert alert-info">
                            <i class="fas fa-shield-alt"></i>
                            <strong>Fournisseur protégé</strong><br>
                            Ce fournisseur fait partie de la configuration par défaut et ne peut pas être supprimé.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Test Configuration Modal --}}
<div class="modal fade" id="testModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tester la configuration IMAP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="testForm">
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Email de test</label>
                        <input type="email" class="form-control" id="test_email" name="test_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="test_password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="test_password" name="test_password" required>
                    </div>
                </form>
                <div id="testResult" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" onclick="runTest()">
                    <i class="fas fa-play"></i> Lancer le test
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function testConfiguration() {
    const modal = new bootstrap.Modal(document.getElementById('testModal'));
    modal.show();
}

function runTest() {
    const email = document.getElementById('test_email').value;
    const password = document.getElementById('test_password').value;
    const resultDiv = document.getElementById('testResult');
    
    if (!email || !password) {
        alert('Veuillez remplir tous les champs');
        return;
    }
    
    // Show loading
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Test en cours...</div>';
    resultDiv.style.display = 'block';
    
    // Make AJAX request
    fetch('{{ route("admin.imap-providers.test", $imapProvider) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            test_email: email,
            test_password: password
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '<div class="alert alert-success">';
            html += '<i class="fas fa-check"></i> ' + data.message;
            
            if (data.details) {
                html += '<hr class="my-2">';
                html += '<small>';
                html += '<strong>Configuration testée :</strong><br>';
                html += 'Host: ' + data.details.host + '<br>';
                html += 'Port: ' + data.details.port + '<br>';
                html += 'Encryption: ' + data.details.encryption + '<br>';
                
                if (data.details.folders && data.details.folders.length > 0) {
                    html += '<br><strong>Dossiers trouvés :</strong><br>';
                    data.details.folders.forEach(folder => {
                        html += '• ' + folder + '<br>';
                    });
                    if (data.details.folders_count > data.details.folders.length) {
                        html += '• ... et ' + (data.details.folders_count - data.details.folders.length) + ' autres<br>';
                    }
                }
                html += '</small>';
            }
            html += '</div>';
            resultDiv.innerHTML = html;
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Erreur de connexion au serveur</div>';
    });
}
</script>
@endpush
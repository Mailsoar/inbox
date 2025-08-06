@extends('layouts.admin')

@section('page-title', 'Ajouter un fournisseur IMAP')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.imap-providers.index') }}">Fournisseurs IMAP</a></li>
            <li class="breadcrumb-item active" aria-current="page">Ajouter</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Ajouter un fournisseur IMAP</h1>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informations du fournisseur</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.imap-providers.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nom technique <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name') }}"
                                           placeholder="ex: example_mail"
                                           required>
                                    <small class="form-text text-muted">
                                        Identifiant unique (lettres, chiffres, tirets et underscores uniquement)
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
                                           value="{{ old('display_name') }}"
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
                                      placeholder="Description du fournisseur email">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Logo du fournisseur</label>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Logo automatique :</strong> 
                                Le logo sera automatiquement chargé depuis <code>/images/providers/{nom_technique}.png</code>
                                <br><small>Si aucun logo n'est trouvé, un logo générique sera utilisé.</small>
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
                                           value="{{ old('imap_host') }}"
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
                                        <option value="993" {{ old('imap_port', '993') == '993' ? 'selected' : '' }}>993 (SSL/TLS)</option>
                                        <option value="143" {{ old('imap_port') == '143' ? 'selected' : '' }}>143 (STARTTLS)</option>
                                        <option value="110" {{ old('imap_port') == '110' ? 'selected' : '' }}>110 (POP3)</option>
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
                                        <option value="ssl" {{ old('encryption', 'ssl') == 'ssl' ? 'selected' : '' }}>SSL/TLS</option>
                                        <option value="tls" {{ old('encryption') == 'tls' ? 'selected' : '' }}>STARTTLS</option>
                                        <option value="none" {{ old('encryption') == 'none' ? 'selected' : '' }}>Aucun</option>
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
                                               {{ old('validate_cert', true) ? 'checked' : '' }}>
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
                                               {{ old('is_active', true) ? 'checked' : '' }}>
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
                                   value="{{ old('common_domains') }}"
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
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Créer le fournisseur
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i> Aide
                    </h6>
                </div>
                <div class="card-body">
                    <h6>Nom technique</h6>
                    <p class="small text-muted">
                        Identifiant unique utilisé en interne. Utilisez des lettres minuscules, chiffres et underscores.
                    </p>

                    <h6>Configuration IMAP</h6>
                    <p class="small text-muted">
                        Les paramètres IMAP seront utilisés pour auto-configurer les comptes email des utilisateurs.
                    </p>

                    <h6>Domaines associés</h6>
                    <p class="small text-muted">
                        Lorsqu'un utilisateur saisit une adresse email avec l'un de ces domaines, 
                        le fournisseur sera automatiquement sélectionné.
                    </p>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important :</strong> Testez toujours la configuration IMAP avec un compte réel avant d'activer le fournisseur.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
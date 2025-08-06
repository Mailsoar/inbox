@extends('layouts.admin')

@section('title', 'Nouveau Provider Email')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Nouveau Provider Email</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.email-providers.store') }}" method="POST">
                        @csrf
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Nom technique <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name') }}" required
                                       placeholder="gmail, outlook, etc.">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">Nom unique en minuscules sans espaces</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="display_name" class="form-label">Nom d'affichage <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('display_name') is-invalid @enderror" 
                                       id="display_name" name="display_name" value="{{ old('display_name') }}" required
                                       placeholder="Gmail, Microsoft Outlook, etc.">
                                @error('display_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                    <option value="">Sélectionner un type</option>
                                    <option value="b2c" {{ old('type') == 'b2c' ? 'selected' : '' }}>B2C (Grand public)</option>
                                    <option value="b2b" {{ old('type') == 'b2b' ? 'selected' : '' }}>B2B (Entreprise)</option>
                                    <option value="antispam" {{ old('type') == 'antispam' ? 'selected' : '' }}>Antispam</option>
                                    <option value="temporary" {{ old('type') == 'temporary' ? 'selected' : '' }}>Temporaire</option>
                                    <option value="blacklisted" {{ old('type') == 'blacklisted' ? 'selected' : '' }}>Blacklisté</option>
                                    <option value="discontinued" {{ old('type') == 'discontinued' ? 'selected' : '' }}>Discontinué</option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-4">
                                <label for="detection_priority" class="form-label">Priorité de détection <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('detection_priority') is-invalid @enderror" 
                                       id="detection_priority" name="detection_priority" value="{{ old('detection_priority', 100) }}" 
                                       min="1" max="999" required>
                                @error('detection_priority')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">1 = priorité haute, 999 = priorité basse</small>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Statut</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="is_valid" name="is_valid" value="1" 
                                           {{ old('is_valid', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_valid">
                                        Provider valide
                                    </label>
                                </div>
                                <small class="form-text text-muted">Décocher pour bloquer ce provider</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="domains" class="form-label">Domaines (un par ligne)</label>
                            <textarea class="form-control @error('domains') is-invalid @enderror" 
                                      id="domains" name="domains" rows="4" 
                                      placeholder="gmail.com&#10;googlemail.com">{{ old('domains') }}</textarea>
                            @error('domains')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Liste des domaines email associés à ce provider</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mx_patterns" class="form-label">Patterns MX (un par ligne)</label>
                            <textarea class="form-control @error('mx_patterns') is-invalid @enderror" 
                                      id="mx_patterns" name="mx_patterns" rows="4" 
                                      placeholder="google.com&#10;aspmx.l.google.com&#10;*.google.com">{{ old('mx_patterns') }}</textarea>
                            @error('mx_patterns')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Patterns des serveurs MX (supporte les wildcards avec *)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                      id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Conseil :</strong> Pour bloquer des emails, utilisez les types "temporary", "blacklisted" ou "discontinued", 
                            ou décochez "Provider valide".
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.email-providers.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Créer le provider
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
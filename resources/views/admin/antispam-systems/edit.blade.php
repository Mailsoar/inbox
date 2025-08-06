@extends('layouts.admin')

@section('title', 'Modifier le système anti-spam')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.antispam-systems.index') }}">Systèmes anti-spam</a></li>
            <li class="breadcrumb-item active">{{ $antispamSystem->display_name }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Modifier le système anti-spam</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.antispam-systems.update', $antispamSystem) }}" method="POST" id="editForm">
                        @csrf
                        @method('PUT')
                        
                        {{-- Name (readonly) --}}
                        <div class="mb-3">
                            <label for="name" class="form-label">Identifiant système</label>
                            <input type="text" class="form-control-plaintext" id="name" 
                                value="{{ $antispamSystem->name }}" readonly>
                        </div>

                        {{-- Display Name --}}
                        <div class="mb-3">
                            <label for="display_name" class="form-label">Nom d'affichage <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('display_name') is-invalid @enderror" 
                                id="display_name" name="display_name" 
                                value="{{ old('display_name', $antispamSystem->display_name) }}" required>
                            @error('display_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                id="description" name="description" rows="3">{{ old('description', $antispamSystem->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Header Patterns --}}
                        <div class="mb-4">
                            <label class="form-label">Patterns de détection d'en-têtes <span class="text-danger">*</span></label>
                            <p class="text-muted small">
                                Entrez les patterns d'en-têtes email qui identifient ce système anti-spam.
                            </p>
                            
                            <div id="patternsContainer">
                                @forelse(old('header_patterns', $antispamSystem->header_patterns) as $pattern)
                                    <div class="pattern-item mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="header_patterns[]" 
                                                value="{{ $pattern }}" placeholder="Ex: X-MyAntispam-Score" required>
                                            <button type="button" class="btn btn-outline-danger" onclick="removePattern(this)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="pattern-item mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="header_patterns[]" 
                                                placeholder="Ex: X-MyAntispam-Score" required>
                                            <button type="button" class="btn btn-outline-danger" onclick="removePattern(this)" style="display: none;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                            
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addPattern()">
                                <i class="fas fa-plus"></i> Ajouter un pattern
                            </button>
                            
                            @error('header_patterns')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            @error('header_patterns.*')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        {{-- MX Patterns --}}
                        <div class="mb-4">
                            <label class="form-label">Patterns MX (pour détection automatique)</label>
                            <p class="text-muted small">
                                Entrez les patterns de serveurs MX pour détecter automatiquement ce système lors de l'ajout d'un compte.
                            </p>
                            
                            <div id="mxPatternsContainer">
                                @forelse(old('mx_patterns', $antispamSystem->mx_patterns ?? []) as $pattern)
                                    <div class="mx-pattern-item mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="mx_patterns[]" 
                                                value="{{ $pattern }}" placeholder="Ex: pphosted.com, *.protection.outlook.com">
                                            <button type="button" class="btn btn-outline-danger" onclick="removeMxPattern(this)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="mx-pattern-item mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="mx_patterns[]" 
                                                placeholder="Ex: pphosted.com, *.protection.outlook.com">
                                            <button type="button" class="btn btn-outline-danger" onclick="removeMxPattern(this)" style="display: none;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                            
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addMxPattern()">
                                <i class="fas fa-plus"></i> Ajouter un pattern MX
                            </button>
                            
                            @error('mx_patterns')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            @error('mx_patterns.*')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Status --}}
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                    value="1" {{ old('is_active', $antispamSystem->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Système actif
                                </label>
                            </div>
                        </div>

                        {{-- Test Section --}}
                        <div class="mb-4">
                            <h6>Tester les patterns</h6>
                            <div class="mb-2">
                                <label for="testHeaders" class="form-label">Collez des en-têtes email pour tester</label>
                                <textarea class="form-control" id="testHeaders" rows="5" 
                                    placeholder="X-Spam-Score: 5.2&#10;X-MyAntispam-Score: HIGH&#10;..."></textarea>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="testPatterns()">
                                <i class="fas fa-flask"></i> Tester
                            </button>
                            <div id="testResults" class="mt-2"></div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                            <a href="{{ route('admin.antispam-systems.index') }}" class="btn btn-outline-secondary">
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informations</h5>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Comptes utilisant ce système</dt>
                        <dd><span class="badge bg-primary">{{ $antispamSystem->emailAccounts()->count() }}</span></dd>
                        <dt>Créé le</dt>
                        <dd>{{ $antispamSystem->created_at->format('d/m/Y H:i') }}</dd>
                        <dt>Dernière modification</dt>
                        <dd>{{ $antispamSystem->updated_at->format('d/m/Y H:i') }}</dd>
                    </dl>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Aide</h5>
                </div>
                <div class="card-body">
                    <h6>Exemples de patterns d'en-têtes</h6>
                    <ul class="small">
                        <li><code>X-Spam-Score</code> - En-tête exact</li>
                        <li><code>X-MyFilter-</code> - Préfixe d'en-tête</li>
                        <li><code>SpamAssassin</code> - Texte dans l'en-tête</li>
                    </ul>
                    
                    <h6 class="mt-3">Exemples de patterns MX</h6>
                    <ul class="small">
                        <li><code>pphosted.com</code> - Domaine exact</li>
                        <li><code>*.protection.outlook.com</code> - Sous-domaine wildcard</li>
                        <li><code>barracudanetworks.com</code> - Partie du nom MX</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addPattern() {
    const container = document.getElementById('patternsContainer');
    const newItem = document.createElement('div');
    newItem.className = 'pattern-item mb-2';
    newItem.innerHTML = `
        <div class="input-group">
            <input type="text" class="form-control" name="header_patterns[]" 
                placeholder="Ex: X-MyAntispam-Score" required>
            <button type="button" class="btn btn-outline-danger" onclick="removePattern(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(newItem);
    updateRemoveButtons();
}

function removePattern(button) {
    button.closest('.pattern-item').remove();
    updateRemoveButtons();
}

function updateRemoveButtons() {
    const items = document.querySelectorAll('.pattern-item');
    items.forEach((item, index) => {
        const removeBtn = item.querySelector('.btn-outline-danger');
        if (removeBtn) {
            removeBtn.style.display = items.length > 1 ? '' : 'none';
        }
    });
}

function testPatterns() {
    const patterns = [];
    document.querySelectorAll('input[name="header_patterns[]"]').forEach(input => {
        if (input.value.trim()) {
            patterns.push(input.value.trim());
        }
    });
    
    const headers = document.getElementById('testHeaders').value;
    const resultsDiv = document.getElementById('testResults');
    
    if (!headers.trim()) {
        resultsDiv.innerHTML = '<div class="alert alert-warning">Veuillez coller des en-têtes email</div>';
        return;
    }
    
    if (patterns.length === 0) {
        resultsDiv.innerHTML = '<div class="alert alert-warning">Veuillez définir au moins un pattern</div>';
        return;
    }
    
    fetch('{{ route("admin.antispam-systems.test-patterns") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ patterns, headers })
    })
    .then(response => response.json())
    .then(data => {
        if (data.matched) {
            resultsDiv.innerHTML = `
                <div class="alert alert-success">
                    <strong>Correspondance trouvée !</strong><br>
                    Patterns détectés : ${data.matches.map(m => `<code>${m}</code>`).join(', ')}
                </div>
            `;
        } else {
            resultsDiv.innerHTML = '<div class="alert alert-info">Aucune correspondance trouvée</div>';
        }
    })
    .catch(error => {
        resultsDiv.innerHTML = '<div class="alert alert-danger">Erreur lors du test</div>';
    });
}

// MX Patterns functions
function addMxPattern() {
    const container = document.getElementById('mxPatternsContainer');
    const newItem = document.createElement('div');
    newItem.className = 'mx-pattern-item mb-2';
    newItem.innerHTML = `
        <div class="input-group">
            <input type="text" class="form-control" name="mx_patterns[]" 
                placeholder="Ex: pphosted.com, *.protection.outlook.com">
            <button type="button" class="btn btn-outline-danger" onclick="removeMxPattern(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(newItem);
    updateMxRemoveButtons();
}

function removeMxPattern(button) {
    button.closest('.mx-pattern-item').remove();
    updateMxRemoveButtons();
}

function updateMxRemoveButtons() {
    const items = document.querySelectorAll('.mx-pattern-item');
    items.forEach((item, index) => {
        const removeBtn = item.querySelector('.btn-outline-danger');
        if (removeBtn) {
            removeBtn.style.display = items.length > 1 ? '' : 'none';
        }
    });
}

// Initialize
updateRemoveButtons();
updateMxRemoveButtons();
</script>
@endsection
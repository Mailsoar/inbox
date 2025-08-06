@extends('layouts.admin')

@section('title', 'Nouveau système anti-spam')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.antispam-systems.index') }}">Systèmes anti-spam</a></li>
            <li class="breadcrumb-item active">Nouveau système</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Créer un système anti-spam</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.antispam-systems.store') }}" method="POST" id="createForm">
                        @csrf
                        
                        {{-- Name --}}
                        <div class="mb-3">
                            <label for="name" class="form-label">Identifiant système <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                id="name" name="name" value="{{ old('name') }}" 
                                pattern="[a-z0-9_]+" required>
                            <small class="form-text text-muted">
                                Lettres minuscules, chiffres et underscores uniquement. Ex: mon_antispam
                            </small>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Display Name --}}
                        <div class="mb-3">
                            <label for="display_name" class="form-label">Nom d'affichage <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('display_name') is-invalid @enderror" 
                                id="display_name" name="display_name" value="{{ old('display_name') }}" required>
                            <small class="form-text text-muted">
                                Nom affiché dans l'interface
                            </small>
                            @error('display_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Header Patterns --}}
                        <div class="mb-4">
                            <label class="form-label">Patterns de détection <span class="text-danger">*</span></label>
                            <p class="text-muted small">
                                Entrez les patterns d'en-têtes email qui identifient ce système anti-spam.
                                Les patterns sont sensibles à la casse.
                            </p>
                            
                            <div id="patternsContainer">
                                <div class="pattern-item mb-2">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="header_patterns[]" 
                                            placeholder="Ex: X-MyAntispam-Score" required>
                                        <button type="button" class="btn btn-outline-danger" onclick="removePattern(this)" style="display: none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
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
                                <div class="mx-pattern-item mb-2">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="mx_patterns[]" 
                                            placeholder="Ex: pphosted.com, *.protection.outlook.com">
                                        <button type="button" class="btn btn-outline-danger" onclick="removeMxPattern(this)" style="display: none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
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
                                <i class="fas fa-save"></i> Créer le système
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
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Aide</h5>
                </div>
                <div class="card-body">
                    <h6>Exemples de patterns</h6>
                    <ul class="small">
                        <li><code>X-Spam-Score</code> - En-tête exact</li>
                        <li><code>X-MyFilter-</code> - Préfixe d'en-tête</li>
                        <li><code>SpamAssassin</code> - Texte dans l'en-tête</li>
                    </ul>
                    
                    <h6 class="mt-3">Conseils</h6>
                    <ul class="small text-muted">
                        <li>Utilisez des patterns spécifiques à votre système</li>
                        <li>Testez avec de vrais en-têtes email</li>
                        <li>Évitez les patterns trop génériques</li>
                        <li>Les patterns sont recherchés dans tous les en-têtes</li>
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

// Initialize
updateRemoveButtons();
</script>
@endsection
@extends('layouts.admin')

@section('title', 'Gestion des Règles de Filtrage')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Règles de filtrage</li>
        </ol>
    </nav>

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-filter text-muted me-2"></i>
            Règles de filtrage
        </h1>
        <div>
            <a href="{{ route('admin.filter-rules.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nouvelle règle
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                                <i class="fas fa-filter fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total Règles</h6>
                            <h3 class="mb-0">{{ $rules->total() }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 text-success rounded-circle p-3">
                                <i class="fas fa-check-circle fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Actives</h6>
                            <h3 class="mb-0">{{ \App\Models\FilterRule::where('is_active', true)->count() }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-3">
                                <i class="fas fa-ban fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Blocages</h6>
                            <h3 class="mb-0">{{ \App\Models\FilterRule::where('action', 'block')->count() }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 text-info rounded-circle p-3">
                                <i class="fas fa-globe fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Domaines</h6>
                            <h3 class="mb-0">{{ \App\Models\FilterRule::where('type', 'domain')->count() }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.filter-rules.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="ip" {{ request('type') == 'ip' ? 'selected' : '' }}>IP</option>
                        <option value="domain" {{ request('type') == 'domain' ? 'selected' : '' }}>Domaine</option>
                        <option value="mx" {{ request('type') == 'mx' ? 'selected' : '' }}>MX</option>
                        <option value="email_pattern" {{ request('type') == 'email_pattern' ? 'selected' : '' }}>Pattern Email</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Action</label>
                    <select name="action" class="form-select" onchange="this.form.submit()">
                        <option value="">Toutes</option>
                        <option value="block" {{ request('action') == 'block' ? 'selected' : '' }}>Bloquer</option>
                        <option value="allow" {{ request('action') == 'allow' ? 'selected' : '' }}>Autoriser</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select name="is_active" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Actif</option>
                        <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Inactif</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Recherche</label>
                    <input type="text" name="search" class="form-control" 
                        placeholder="Valeur, description..." value="{{ request('search') }}">
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Valeur</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rules as $rule)
                        <tr>
                            <td>
                                <span class="badge bg-info">{{ strtoupper($rule->type) }}</span>
                            </td>
                            <td>
                                <code>{{ $rule->value }}</code>
                                @if($rule->type === 'email_pattern' && $rule->value === 'normalization_settings' && $rule->options)
                                    <div class="small text-muted mt-1">
                                        @if($rule->options['normalize_gmail_dots'] ?? false)
                                            <i class="fas fa-check text-success"></i> Normaliser points Gmail
                                        @endif
                                        @if($rule->options['normalize_plus_aliases'] ?? false)
                                            <i class="fas fa-check text-success"></i> Normaliser alias +
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($rule->action === 'block')
                                    <span class="badge bg-danger">
                                        <i class="fas fa-ban"></i> Bloquer
                                    </span>
                                @else
                                    <span class="badge bg-success">
                                        <i class="fas fa-check"></i> Autoriser
                                    </span>
                                @endif
                            </td>
                            <td>
                                <small>{{ $rule->description }}</small>
                            </td>
                            <td>
                                <form action="{{ route('admin.filter-rules.toggle', $rule) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-link p-0" 
                                            data-bs-toggle="tooltip" 
                                            title="Cliquer pour {{ $rule->is_active ? 'désactiver' : 'activer' }}">
                                        @if($rule->is_active)
                                            <span class="badge bg-success">Actif</span>
                                        @else
                                            <span class="badge bg-secondary">Inactif</span>
                                        @endif
                                    </button>
                                </form>
                            </td>
                            <td>
                                <small>{{ $rule->created_at->format('d/m/Y H:i') }}</small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.filter-rules.edit', $rule) }}" 
                                       class="btn btn-sm btn-outline-secondary"
                                       data-bs-toggle="tooltip"
                                       title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <form action="{{ route('admin.filter-rules.destroy', $rule) }}" 
                                          method="POST" 
                                          class="d-inline" 
                                          data-confirm="Êtes-vous sûr de vouloir supprimer cette règle de filtrage ?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="tooltip"
                                                title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-filter fa-3x mb-3"></i>
                                    <p>Aucune règle de filtrage trouvée</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            {{ $rules->links() }}
        </div>
    </div>

    {{-- Test Section --}}
    <div class="card shadow-sm mt-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-vial me-2"></i>
                Tester les règles
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Type de test</label>
                    <select id="test_type" class="form-select">
                        <option value="email">Email</option>
                        <option value="ip">Adresse IP</option>
                        <option value="domain">Domaine</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Valeur à tester</label>
                    <input type="text" id="test_value" class="form-control" placeholder="Entrez une valeur...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-primary w-100" onclick="testRule()">
                        <i class="fas fa-flask"></i> Tester
                    </button>
                </div>
            </div>
            
            <div id="test_result" class="mt-3"></div>
        </div>
    </div>
</div>

<script>
// Initialiser les tooltips Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function testRule() {
    const type = document.getElementById('test_type').value;
    const value = document.getElementById('test_value').value;
    const resultDiv = document.getElementById('test_result');
    
    if (!value) {
        showToast('warning', 'Veuillez entrer une valeur à tester');
        return;
    }
    
    // Afficher un spinner
    resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    
    fetch('{{ route('admin.filter-rules.test') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            test_type: type,
            test_value: value
        })
    })
    .then(response => response.json())
    .then(data => {
        let html = '<div class="alert ';
        
        if (type === 'email') {
            html += data.domain_blocked ? 'alert-danger' : 'alert-success';
            html += '">';
            html += '<h6 class="alert-heading">';
            html += data.domain_blocked ? '<i class="fas fa-ban"></i> Domaine bloqué' : '<i class="fas fa-check-circle"></i> Email autorisé';
            html += '</h6>';
            html += '<hr>';
            html += '<p class="mb-0">Email normalisé : <strong>' + data.normalized + '</strong></p>';
        } else {
            html += data.blocked ? 'alert-danger' : 'alert-success';
            html += '">';
            html += '<h6 class="alert-heading">';
            html += data.blocked ? '<i class="fas fa-ban"></i> Bloqué' : '<i class="fas fa-check-circle"></i> Autorisé';
            html += '</h6>';
        }
        
        html += '</div>';
        resultDiv.innerHTML = html;
    })
    .catch(error => {
        showToast('danger', 'Erreur lors du test');
        resultDiv.innerHTML = '';
    });
}

function showToast(type, message) {
    // Créer un conteneur pour les toasts s'il n'existe pas
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.position = 'fixed';
        toastContainer.style.top = '20px';
        toastContainer.style.right = '20px';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    // Créer le toast
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    // Afficher le toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    toast.show();
    
    // Supprimer le toast après disparition
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}
</script>
@endsection
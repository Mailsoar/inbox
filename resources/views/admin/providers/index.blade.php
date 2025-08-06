@extends('layouts.admin')

@section('title', 'Gestion des Fournisseurs Email')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Fournisseurs Email</li>
        </ol>
    </nav>

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-server text-muted me-2"></i>
            Fournisseurs Email
        </h1>
        <div>
            <a href="{{ route('admin.providers.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nouveau fournisseur
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
                                <i class="fas fa-server fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total Providers</h6>
                            <h3 class="mb-0">{{ $providers->total() }}</h3>
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
                            <h6 class="text-muted mb-1">Actifs</h6>
                            <h3 class="mb-0">{{ \App\Models\EmailProvider::where('is_active', true)->count() }}</h3>
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
                                <i class="fas fa-shield-alt fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">OAuth</h6>
                            <h3 class="mb-0">{{ \App\Models\EmailProvider::where('supports_oauth', true)->count() }}</h3>
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
                            <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                                <i class="fas fa-users fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">B2C</h6>
                            <h3 class="mb-0">{{ \App\Models\EmailProvider::where('provider_type', 'b2c')->count() }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.providers.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="provider_type" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="b2c" {{ request('provider_type') == 'b2c' ? 'selected' : '' }}>B2C - Grand public</option>
                        <option value="b2b" {{ request('provider_type') == 'b2b' ? 'selected' : '' }}>B2B - Professionnel</option>
                        <option value="custom" {{ request('provider_type') == 'custom' ? 'selected' : '' }}>Personnalisé</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">OAuth</label>
                    <select name="oauth" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="1" {{ request('oauth') == '1' ? 'selected' : '' }}>Avec OAuth</option>
                        <option value="0" {{ request('oauth') == '0' ? 'selected' : '' }}>Sans OAuth</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select name="active" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="1" {{ request('active') == '1' ? 'selected' : '' }}>Actifs</option>
                        <option value="0" {{ request('active') == '0' ? 'selected' : '' }}>Inactifs</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Recherche</label>
                    <input type="text" name="search" class="form-control" 
                        placeholder="Nom, domaine..." value="{{ request('search') }}">
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
                            <th>Fournisseur</th>
                            <th>Type</th>
                            <th>Configuration IMAP</th>
                            <th>OAuth</th>
                            <th>Domaines</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($providers as $provider)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if($provider->logo_url)
                                        <img src="{{ $provider->logo_url }}" alt="{{ $provider->name }}" 
                                            class="me-2" style="height: 24px;">
                                    @endif
                                    <div>
                                        <strong>{{ $provider->display_name }}</strong><br>
                                        <small class="text-muted">{{ $provider->name }}</small>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <span class="badge 
                                    @if($provider->provider_type === 'b2c') bg-info
                                    @elseif($provider->provider_type === 'b2b') bg-primary
                                    @elseif($provider->provider_type === 'custom') bg-secondary
                                    @else bg-warning
                                    @endif">
                                    {{ ucfirst($provider->provider_type) }}
                                </span>
                            </td>
                            
                            <td>
                                @if($provider->imap_host)
                                    <small>
                                        <code>{{ $provider->imap_host }}:{{ $provider->imap_port }}</code><br>
                                        <span class="text-muted">{{ strtoupper($provider->imap_encryption) }}</span>
                                    </small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            
                            <td>
                                @if($provider->supports_oauth)
                                    <span class="badge bg-success">
                                        <i class="fas fa-check"></i> OAuth
                                    </span>
                                @else
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-key"></i> Password
                                    </span>
                                @endif
                            </td>
                            
                            <td>
                                @if($provider->domains)
                                    <span class="badge bg-light text-dark">
                                        {{ count($provider->domains) }} domaine(s)
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            
                            <td>
                                @if($provider->is_active)
                                    <span class="badge bg-success">Actif</span>
                                @else
                                    <span class="badge bg-danger">Inactif</span>
                                @endif
                            </td>
                            
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    {{-- Bouton Test si IMAP configuré --}}
                                    @if($provider->imap_host)
                                    <button class="btn btn-sm btn-outline-primary" 
                                        onclick="testProvider({{ $provider->id }})"
                                        data-bs-toggle="tooltip"
                                        title="Tester la connexion">
                                        <i class="fas fa-plug"></i>
                                    </button>
                                    @endif
                                    
                                    {{-- Bouton Éditer --}}
                                    <a href="{{ route('admin.providers.edit', $provider) }}" 
                                        class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="tooltip"
                                        title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    {{-- Menu dropdown pour les autres actions --}}
                                    <div class="btn-group" role="group">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" 
                                                data-bs-toggle="dropdown" 
                                                aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <form action="{{ route('admin.providers.destroy', $provider) }}" 
                                                    method="POST" 
                                                    data-confirm="Êtes-vous sûr de vouloir supprimer le fournisseur {{ $provider->display_name }} ?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="fas fa-trash me-2"></i> Supprimer
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            {{ $providers->links() }}
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

function testProvider(id) {
    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    
    // Désactiver le bouton et afficher un spinner
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch(`/admin/providers/${id}/test`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        // Créer une notification toast au lieu d'une alerte
        showToast(data.success ? 'success' : 'danger', data.message);
        
        // Restaurer le bouton
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    })
    .catch(error => {
        showToast('danger', 'Erreur lors du test de connexion');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
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
                    ${message.replace(/\n/g, '<br>')}
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
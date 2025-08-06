@extends('layouts.admin')

@section('page-title', 'Fournisseurs IMAP')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Fournisseurs IMAP</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Fournisseurs IMAP</h1>
            <p class="text-muted">Gérez les configurations IMAP prédéfinies</p>
        </div>
        <div>
            <button type="button" class="btn btn-warning me-2" onclick="confirmReset()">
                <i class="fas fa-undo"></i> Réinitialiser
            </button>
            <a href="{{ route('admin.imap-providers.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Ajouter un fournisseur
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-primary">{{ $totalProviders }}</h5>
                    <p class="card-text">Total</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-success">{{ $activeProviders }}</h5>
                    <p class="card-text">Actifs</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Recherche</label>
                    <input type="text" 
                           class="form-control" 
                           id="search" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="Nom, host IMAP...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Statut</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Tous</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Actifs</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactifs</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary me-2">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="{{ route('admin.imap-providers.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Providers Table --}}
    <div class="card">
        <div class="card-body">
            @if($providers->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Logo</th>
                                <th>Nom</th>
                                <th>Host IMAP</th>
                                <th>Port</th>
                                <th>Domaines</th>
                                <th>Comptes</th>
                                <th>Statut</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($providers as $provider)
                                <tr>
                                    <td>
                                        <img src="{{ $provider->getLogoUrl() }}" 
                                             alt="{{ $provider->display_name }}" 
                                             class="provider-logo"
                                             style="width: 32px; height: 32px; object-fit: contain;"
                                             onerror="this.src='/images/providers/generic.svg'">
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $provider->display_name }}</strong>
                                            @if($provider->isProtected())
                                                <span class="badge bg-info ms-1" title="Fournisseur par défaut">
                                                    <i class="fas fa-shield-alt"></i>
                                                </span>
                                            @endif
                                        </div>
                                        <small class="text-muted">{{ $provider->name }}</small>
                                    </td>
                                    <td>
                                        <code>{{ $provider->imap_host }}</code>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $provider->imap_port }}</span>
                                        <small class="text-muted d-block">{{ strtoupper($provider->encryption) }}</small>
                                    </td>
                                    <td>
                                        @if(!empty($provider->common_domains))
                                            @foreach(array_slice($provider->common_domains, 0, 2) as $domain)
                                                <span class="badge bg-light text-dark me-1">{{ $domain }}</span>
                                            @endforeach
                                            @if(count($provider->common_domains) > 2)
                                                <span class="text-muted">+{{ count($provider->common_domains) - 2 }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">Aucun</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ $provider->emailAccounts()->count() }}</span>
                                    </td>
                                    <td>
                                        @if($provider->is_active)
                                            <span class="badge bg-success">Actif</span>
                                        @else
                                            <span class="badge bg-secondary">Inactif</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.imap-providers.edit', $provider) }}" 
                                               class="btn btn-outline-primary btn-sm" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @if(!$provider->isProtected())
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-sm" 
                                                        title="Supprimer"
                                                        onclick="confirmDelete('{{ $provider->display_name }}', '{{ route('admin.imap-providers.destroy', $provider) }}')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-center">
                    {{ $providers->links() }}
                </div>
            @else
                <div class="text-center py-4">
                    <i class="fas fa-server fa-3x text-muted mb-3"></i>
                    <h5>Aucun fournisseur trouvé</h5>
                    <p class="text-muted">Commencez par ajouter votre premier fournisseur IMAP.</p>
                    <a href="{{ route('admin.imap-providers.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Ajouter un fournisseur
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer le fournisseur <strong id="deleteProviderName"></strong> ?</p>
                <p class="text-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Cette action est irréversible.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function confirmDelete(providerName, deleteUrl) {
    document.getElementById('deleteProviderName').textContent = providerName;
    document.getElementById('deleteForm').action = deleteUrl;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function confirmReset() {
    if (confirm('Êtes-vous sûr de vouloir réinitialiser tous les fournisseurs IMAP aux valeurs d\'usine ?\n\nCette action :\n- Restaurera tous les fournisseurs par défaut\n- Désactivera les fournisseurs personnalisés (sauf "custom")\n- Ne supprimera aucun compte email existant')) {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("admin.imap-providers.reset") }}';
        
        // Add CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);
        
        // Submit form
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endpush
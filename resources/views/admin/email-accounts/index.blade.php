@extends('layouts.admin')

@section('title', 'Gestion des Comptes Email')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Comptes Email</li>
        </ol>
    </nav>

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-envelope text-muted me-2"></i>
            Comptes Email
        </h1>
        <a href="{{ route('admin.email-accounts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Ajouter un compte
        </a>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                                <i class="fas fa-envelope fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total des comptes</h6>
                            <h3 class="mb-0">{{ $totalAccounts }}</h3>
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
                            <h6 class="text-muted mb-1">Comptes actifs</h6>
                            <h3 class="mb-0">{{ $activeAccounts }}</h3>
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
                            <h3 class="mb-0">{{ $oauthAccounts }}</h3>
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
                                <i class="fas fa-plug fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Connexions OK</h6>
                            <h3 class="mb-0">{{ $connectedAccounts }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtres --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Recherche</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Email, nom..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Connection Type</label>
                    <select name="provider" class="form-select">
                        <option value="">Tous</option>
                        @foreach($providers as $provider)
                            <option value="{{ $provider }}" {{ request('provider') == $provider ? 'selected' : '' }}>
                                {{ ucfirst($provider) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Type</label>
                    <select name="account_type" class="form-select">
                        <option value="">Tous</option>
                        <option value="b2b" {{ request('account_type') == 'b2b' ? 'selected' : '' }}>B2B</option>
                        <option value="b2c" {{ request('account_type') == 'b2c' ? 'selected' : '' }}>B2C</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Statut</label>
                    <select name="status" class="form-select">
                        <option value="">Tous</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Actifs</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactifs</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary me-2">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="{{ route('admin.email-accounts.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tableau des comptes --}}
    <div class="card shadow-sm">
        <div class="card-body">
            @if($accounts->isEmpty())
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Aucun compte email trouvé</p>
                    <a href="{{ route('admin.email-accounts.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Ajouter le premier compte
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Fournisseur</th>
                                <th>Connection Type</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Connexion</th>
                                <th>Test received (30j)</th>
                                <th>Dernière vérif.</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accounts as $account)
                            <tr>
                                <td>
                                    <div>
                                        <strong>{{ $account->email }}</strong>
                                        @if($account->name)
                                            <br><small class="text-muted">{{ $account->name }}</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $realProvider = $account->getRealProvider();
                                    @endphp
                                    @if($realProvider === 'Gmail')
                                        <i class="fab fa-google text-danger"></i> Gmail
                                    @elseif($realProvider === 'Google Workspace')
                                        <i class="fab fa-google text-success"></i> Google Workspace
                                    @elseif($realProvider === 'Outlook' || $realProvider === 'Outlook / Hotmail')
                                        <i class="fab fa-microsoft text-primary"></i> Outlook
                                    @elseif($realProvider === 'Microsoft 365')
                                        <i class="fab fa-microsoft text-info"></i> Microsoft 365
                                    @elseif($realProvider === 'Yahoo' || $realProvider === 'Yahoo Mail')
                                        <i class="fab fa-yahoo text-purple"></i> Yahoo
                                    @elseif($realProvider === 'Apple' || $realProvider === 'Apple (iCloud)' || $realProvider === 'iCloud Mail')
                                        <i class="fab fa-apple text-dark"></i> Apple
                                    @elseif($realProvider === 'La Poste')
                                        <i class="fas fa-envelope text-info"></i> La Poste
                                    @elseif($realProvider === 'Orange')
                                        <i class="fas fa-envelope text-warning"></i> Orange
                                    @elseif($realProvider === 'Free')
                                        <i class="fas fa-envelope text-secondary"></i> Free
                                    @elseif($realProvider === 'Proofpoint')
                                        <i class="fas fa-shield-alt text-primary"></i> Proofpoint
                                    @else
                                        <i class="fas fa-envelope"></i> {{ $realProvider }}
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $connectionType = ucfirst($account->provider);
                                        $authMethod = '';
                                        
                                        // Déterminer la méthode d'authentification
                                        if ($account->auth_type === 'oauth' || $account->oauth_token) {
                                            $authMethod = ' (OAuth)';
                                        } elseif ($account->provider === 'yahoo' || $account->requires_app_password) {
                                            $authMethod = ' (App Password)';
                                        } elseif ($account->provider === 'imap') {
                                            $authMethod = ' (Password)';
                                        } elseif ($account->password) {
                                            $authMethod = ' (Password)';
                                        }
                                        
                                        // Pour Outlook/Microsoft, c'est toujours OAuth
                                        if (in_array($account->provider, ['outlook', 'microsoft'])) {
                                            $authMethod = ' (OAuth)';
                                        }
                                    @endphp
                                    <span class="badge 
                                        @if($account->provider === 'gmail') bg-danger
                                        @elseif(in_array($account->provider, ['outlook', 'microsoft'])) bg-primary
                                        @elseif($account->provider === 'yahoo') bg-purple
                                        @else bg-secondary
                                        @endif">
                                        {{ $connectionType }}{{ $authMethod }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge 
                                        @if($account->account_type === 'b2b') bg-info
                                        @elseif($account->account_type === 'b2c') bg-warning
                                        @else bg-secondary
                                        @endif">
                                        {{ strtoupper($account->account_type ?? 'N/A') }}
                                    </span>
                                </td>
                                <td>
                                    @if($account->is_active)
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle"></i> Actif
                                        </span>
                                    @else
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times-circle"></i> Inactif
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($account->connection_status === 'success')
                                        <span class="badge bg-success">
                                            <i class="fas fa-link"></i> OK
                                        </span>
                                    @elseif($account->connection_status === 'failed')
                                        <span class="badge bg-danger" 
                                              data-bs-toggle="tooltip" 
                                              title="{{ $account->connection_error ?? 'Erreur inconnue' }}">
                                            <i class="fas fa-unlink"></i> Échoué
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-question"></i> Inconnu
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        {{ $account->emails_last_30_days ?? 0 }}
                                    </span>
                                </td>
                                <td>
                                    @if($account->last_connection_check)
                                        <small>{{ $account->last_connection_check->diffForHumans() }}</small>
                                    @else
                                        <small class="text-muted">Jamais</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        {{-- Test Connection --}}
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                onclick="testConnection({{ $account->id }}, event)"
                                                data-bs-toggle="tooltip" 
                                                title="Tester la connexion">
                                            <i class="fas fa-plug"></i>
                                        </button>
                                        
                                        {{-- Edit --}}
                                        <a href="{{ route('admin.email-accounts.edit', $account) }}" 
                                           class="btn btn-sm btn-outline-secondary"
                                           data-bs-toggle="tooltip"
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        {{-- Dropdown menu --}}
                                        <div class="btn-group" role="group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" 
                                                    data-bs-toggle="dropdown" 
                                                    aria-expanded="false">
                                                <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.email-accounts.configure-antispam', $account) }}">
                                                        <i class="fas fa-shield-alt me-2"></i> Configuration anti-spam
                                                    </a>
                                                </li>
                                                <li>
                                                    <form action="{{ route('admin.email-accounts.toggle-status', $account) }}" method="POST">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="dropdown-item">
                                                            @if($account->is_active)
                                                                <i class="fas fa-toggle-off me-2 text-danger"></i> Désactiver
                                                            @else
                                                                <i class="fas fa-toggle-on me-2 text-success"></i> Activer
                                                            @endif
                                                        </button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('admin.email-accounts.destroy', $account) }}" 
                                                          method="POST" 
                                                          data-confirm="Êtes-vous sûr de vouloir supprimer le compte {{ $account->email }} ?">
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

                {{-- Pagination --}}
                <div class="mt-3">
                    {{ $accounts->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Custom styles --}}
<style>
.bg-purple {
    background-color: #6f42c1 !important;
}
</style>

<script>
// Initialiser les tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function testConnection(accountId, event) {
    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch(`/admin/email-accounts/${accountId}/test`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        showToast(data.success ? 'success' : 'danger', data.message);
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        
        // Recharger la page après 2 secondes pour voir le nouveau statut
        if (data.success) {
            setTimeout(() => window.location.reload(), 2000);
        }
    })
    .catch(error => {
        showToast('danger', 'Erreur lors du test de connexion');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}

function showToast(type, message) {
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
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}
</script>
@endsection
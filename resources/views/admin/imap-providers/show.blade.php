@extends('layouts.admin')

@section('page-title', $imapProvider->display_name)

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
                     style="width: 40px; height: 40px; object-fit: contain;"
                     onerror="this.src='/images/providers/generic.svg'">
                {{ $imapProvider->display_name }}
                @if($imapProvider->isProtected())
                    <span class="badge bg-info ms-2" title="Fournisseur par défaut">
                        <i class="fas fa-shield-alt"></i> Protégé
                    </span>
                @endif
                @if($imapProvider->is_active)
                    <span class="badge bg-success ms-1">Actif</span>
                @else
                    <span class="badge bg-secondary ms-1">Inactif</span>
                @endif
            </h1>
            @if($imapProvider->description)
                <p class="text-muted">{{ $imapProvider->description }}</p>
            @endif
        </div>
        <div>
            <a href="{{ route('admin.imap-providers.edit', $imapProvider) }}" class="btn btn-primary">
                <i class="fas fa-edit"></i> Modifier
            </a>
            <a href="{{ route('admin.imap-providers.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <div class="row">
        {{-- Configuration Details --}}
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cog"></i> Configuration IMAP
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Serveur</h6>
                            <p class="text-muted mb-3">
                                <code class="bg-light p-2 rounded">{{ $imapProvider->imap_host }}</code>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <h6>Port</h6>
                            <p class="text-muted mb-3">
                                <span class="badge bg-secondary">{{ $imapProvider->imap_port }}</span>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <h6>Chiffrement</h6>
                            <p class="text-muted mb-3">
                                <span class="badge bg-primary">{{ strtoupper($imapProvider->encryption) }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6>Validation SSL</h6>
                            <p class="text-muted mb-3">
                                @if($imapProvider->validate_cert)
                                    <span class="badge bg-success">
                                        <i class="fas fa-check"></i> Activée
                                    </span>
                                @else
                                    <span class="badge bg-warning">
                                        <i class="fas fa-exclamation-triangle"></i> Désactivée
                                    </span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6>Nom technique</h6>
                            <p class="text-muted mb-3">
                                <code class="bg-light p-1 rounded">{{ $imapProvider->name }}</code>
                            </p>
                        </div>
                    </div>

                    <h6>Domaines associés</h6>
                    <div class="mb-3">
                        @if(!empty($imapProvider->common_domains))
                            @foreach($imapProvider->common_domains as $domain)
                                <span class="badge bg-light text-dark me-1 mb-1">{{ $domain }}</span>
                            @endforeach
                        @else
                            <span class="text-muted">Aucun domaine configuré</span>
                        @endif
                    </div>

                    <h6>Logo</h6>
                    <div class="mb-3">
                        <img src="{{ $imapProvider->getLogoUrl() }}" 
                             alt="{{ $imapProvider->display_name }}" 
                             style="max-width: 200px; max-height: 80px; object-fit: contain;"
                             class="border p-2 rounded"
                             onerror="this.src='/images/providers/generic.svg'">
                        <div class="mt-2">
                            @if($imapProvider->hasCustomLogo())
                                <span class="badge bg-success">
                                    <i class="fas fa-check"></i> Logo personnalisé
                                </span>
                            @else
                                <span class="badge bg-secondary">
                                    <i class="fas fa-image"></i> Logo générique
                                </span>
                            @endif
                            <small class="d-block text-muted mt-1">
                                Fichier : <code>/images/providers/{{ $imapProvider->name }}.svg</code>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Associated Email Accounts --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-envelope"></i> Comptes email associés
                        <span class="badge bg-primary ms-2">{{ $stats['total_accounts'] }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    @if($emailAccounts->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Nom</th>
                                        <th>Statut</th>
                                        <th>Créé le</th>
                                        <th width="100">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($emailAccounts as $account)
                                        <tr>
                                            <td>{{ $account->email }}</td>
                                            <td>{{ $account->name ?? '-' }}</td>
                                            <td>
                                                @if($account->is_active)
                                                    <span class="badge bg-success">Actif</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactif</span>
                                                @endif
                                            </td>
                                            <td>{{ $account->created_at->format('d/m/Y') }}</td>
                                            <td>
                                                <a href="{{ route('admin.email-accounts.edit', $account) }}" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($stats['total_accounts'] > $emailAccounts->count())
                            <div class="text-center mt-3">
                                <a href="{{ route('admin.email-accounts.index', ['provider' => 'imap', 'imap_provider' => $imapProvider->id]) }}" 
                                   class="btn btn-outline-primary btn-sm">
                                    Voir tous les comptes ({{ $stats['total_accounts'] }})
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
                            <h6>Aucun compte associé</h6>
                            <p class="text-muted">Ce fournisseur n'est utilisé par aucun compte email.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- Stats --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-chart-bar"></i> Statistiques
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h4 class="text-primary">{{ $stats['total_accounts'] }}</h4>
                            <small class="text-muted">Comptes total</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success">{{ $stats['active_accounts'] }}</h4>
                            <small class="text-muted">Comptes actifs</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Information --}}
            <div class="card mb-4">
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
                            Ce fournisseur fait partie de la configuration par défaut.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-bolt"></i> Actions rapides
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="testConfiguration()">
                            <i class="fas fa-vials"></i> Tester la configuration
                        </button>
                        <a href="{{ route('admin.email-accounts.create') }}" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-plus"></i> Créer un compte IMAP
                        </a>
                        @if(!$imapProvider->isProtected())
                            <button type="button" 
                                    class="btn btn-outline-danger btn-sm" 
                                    onclick="confirmDelete()">
                                <i class="fas fa-trash"></i> Supprimer le fournisseur
                            </button>
                        @endif
                    </div>
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

{{-- Delete Confirmation Modal --}}
@if(!$imapProvider->isProtected())
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer le fournisseur <strong>{{ $imapProvider->display_name }}</strong> ?</p>
                @if($stats['total_accounts'] > 0)
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Attention :</strong> Ce fournisseur est utilisé par {{ $stats['total_accounts'] }} compte(s) email.
                        La suppression sera bloquée.
                    </div>
                @else
                    <p class="text-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Cette action est irréversible.
                    </p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                @if($stats['total_accounts'] == 0)
                    <form method="POST" action="{{ route('admin.imap-providers.destroy', $imapProvider) }}" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
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
            resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check"></i> ' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Erreur de connexion</div>';
    });
}

function confirmDelete() {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>
@endpush
@endsection
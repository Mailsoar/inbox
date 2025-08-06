@extends('layouts.admin')

@section('title', 'Email Providers')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">Email Providers</h1>
            <p class="text-muted">Gérer les providers email et leurs patterns de détection</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admin.email-providers.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nouveau Provider
            </a>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetModal">
                <i class="fas fa-sync"></i> Réinitialiser
            </button>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Providers</h5>
                    <h2 class="mb-0">{{ $stats['total'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-success">Valides</h5>
                    <h2 class="mb-0 text-success">{{ $stats['valid'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-danger">Bloqués</h5>
                    <h2 class="mb-0 text-danger">{{ $stats['blocked'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Test Email</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#testModal">
                        <i class="fas fa-flask"></i> Tester
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.email-providers.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous les types</option>
                        <option value="b2c" {{ request('type') == 'b2c' ? 'selected' : '' }}>B2C</option>
                        <option value="b2b" {{ request('type') == 'b2b' ? 'selected' : '' }}>B2B</option>
                        <option value="antispam" {{ request('type') == 'antispam' ? 'selected' : '' }}>Antispam</option>
                        <option value="temporary" {{ request('type') == 'temporary' ? 'selected' : '' }}>Temporaire</option>
                        <option value="blacklisted" {{ request('type') == 'blacklisted' ? 'selected' : '' }}>Blacklisté</option>
                        <option value="discontinued" {{ request('type') == 'discontinued' ? 'selected' : '' }}>Discontinué</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous les statuts</option>
                        <option value="valid" {{ request('status') == 'valid' ? 'selected' : '' }}>Valides</option>
                        <option value="blocked" {{ request('status') == 'blocked' ? 'selected' : '' }}>Bloqués</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Recherche</label>
                    <input type="text" name="search" class="form-control" placeholder="Nom, domaine, MX..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filtrer</button>
                    <a href="{{ route('admin.email-providers.index') }}" class="btn btn-outline-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des providers -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Priorité</th>
                        <th>Patterns</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($providers as $provider)
                    <tr>
                        <td>
                            <strong>{{ $provider->display_name }}</strong><br>
                            <small class="text-muted">{{ $provider->name }}</small>
                        </td>
                        <td>
                            @php
                                $typeColors = [
                                    'b2c' => 'primary',
                                    'b2b' => 'info',
                                    'antispam' => 'warning',
                                    'temporary' => 'danger',
                                    'blacklisted' => 'dark',
                                    'discontinued' => 'secondary'
                                ];
                            @endphp
                            <span class="badge bg-{{ $typeColors[$provider->type] ?? 'secondary' }}">
                                {{ strtoupper($provider->type) }}
                            </span>
                        </td>
                        <td>
                            @if($provider->isBlocked())
                                <span class="badge bg-danger">Bloqué</span>
                            @else
                                <span class="badge bg-success">Valide</span>
                            @endif
                        </td>
                        <td>{{ $provider->detection_priority }}</td>
                        <td>
                            <span class="badge bg-light text-dark">{{ $provider->patterns_count }} patterns</span>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="{{ route('admin.email-providers.show', $provider) }}" 
                                   class="btn btn-sm btn-outline-primary" title="Voir">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.email-providers.edit', $provider) }}" 
                                   class="btn btn-sm btn-outline-secondary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.email-providers.destroy', $provider) }}" 
                                      method="POST" class="d-inline" 
                                      onsubmit="return confirm('Supprimer ce provider ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <p class="mb-0">Aucun provider trouvé</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($providers->hasPages())
        <div class="card-footer">
            {{ $providers->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Modal Test Email -->
<div class="modal fade" id="testModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tester la détection d'email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Email à tester</label>
                    <input type="email" class="form-control" id="testEmail" placeholder="test@example.com">
                </div>
                <div id="testResult" style="display: none;">
                    <h6>Résultat :</h6>
                    <pre class="bg-light p-3 rounded"></pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" onclick="testEmail()">Tester</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reset -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Réinitialiser les providers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Cette action va supprimer tous les providers existants et réimporter les providers par défaut.
                </div>
                <p>Êtes-vous sûr de vouloir continuer ?</p>
            </div>
            <div class="modal-footer">
                <form action="{{ route('admin.email-providers.reset') }}" method="POST">
                    @csrf
                    <input type="hidden" name="confirm" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Réinitialiser</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
function testEmail() {
    const email = document.getElementById('testEmail').value;
    if (!email) {
        alert('Veuillez entrer un email');
        return;
    }
    
    fetch('{{ route('admin.email-providers.test') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('testResult').style.display = 'block';
        document.querySelector('#testResult pre').textContent = JSON.stringify(data.result, null, 2);
    })
    .catch(error => {
        alert('Erreur lors du test');
        console.error(error);
    });
}
</script>
@endsection
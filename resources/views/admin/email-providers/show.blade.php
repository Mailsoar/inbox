@extends('layouts.admin')

@section('title', $emailProvider->display_name)

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">{{ $emailProvider->display_name }}</h1>
            <p class="text-muted mb-0">Provider Email - {{ $emailProvider->name }}</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admin.email-providers.edit', $emailProvider) }}" class="btn btn-primary">
                <i class="fas fa-edit"></i> Modifier
            </a>
            <form action="{{ route('admin.email-providers.destroy', $emailProvider) }}" method="POST" class="d-inline" 
                  onsubmit="return confirm('Supprimer ce provider ?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="fas fa-trash"></i> Supprimer
                </button>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Informations générales</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Type</th>
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
                                <span class="badge bg-{{ $typeColors[$emailProvider->type] ?? 'secondary' }}">
                                    {{ strtoupper($emailProvider->type) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Statut</th>
                            <td>
                                @if($emailProvider->isBlocked())
                                    <span class="badge bg-danger">Bloqué</span>
                                @else
                                    <span class="badge bg-success">Valide</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Priorité de détection</th>
                            <td>{{ $emailProvider->detection_priority }}</td>
                        </tr>
                        <tr>
                            <th>Créé le</th>
                            <td>{{ $emailProvider->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        <tr>
                            <th>Mis à jour le</th>
                            <td>{{ $emailProvider->updated_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        @if($emailProvider->notes)
                        <tr>
                            <th>Notes</th>
                            <td>{{ $emailProvider->notes }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Test de détection</h5>
                </div>
                <div class="card-body">
                    @if($testEmails)
                        <p>Exemples d'emails pour ce provider :</p>
                        <ul class="list-unstyled">
                            @foreach($testEmails as $email)
                                <li>
                                    <code>{{ $email }}</code>
                                    <button class="btn btn-sm btn-outline-primary ms-2" onclick="testEmailDetection('{{ $email }}')">
                                        <i class="fas fa-flask"></i> Tester
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    
                    <div class="mt-3">
                        <label class="form-label">Tester un email personnalisé</label>
                        <div class="input-group">
                            <input type="email" class="form-control" id="customTestEmail" placeholder="test@example.com">
                            <button class="btn btn-primary" type="button" onclick="testCustomEmail()">Tester</button>
                        </div>
                    </div>
                    
                    <div id="testResult" class="mt-3" style="display: none;">
                        <h6>Résultat du test :</h6>
                        <pre class="bg-light p-3 rounded"></pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Domaines ({{ $emailProvider->domainPatterns->count() }})</h5>
                </div>
                <div class="card-body">
                    @if($emailProvider->domainPatterns->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Domaine</th>
                                        <th>Ajouté le</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($emailProvider->domainPatterns as $pattern)
                                    <tr>
                                        <td><code>{{ $pattern->pattern }}</code></td>
                                        <td>{{ $pattern->created_at->format('d/m/Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">Aucun domaine configuré</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Patterns MX ({{ $emailProvider->mxPatterns->count() }})</h5>
                </div>
                <div class="card-body">
                    @if($emailProvider->mxPatterns->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Pattern MX</th>
                                        <th>Ajouté le</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($emailProvider->mxPatterns as $pattern)
                                    <tr>
                                        <td><code>{{ $pattern->pattern }}</code></td>
                                        <td>{{ $pattern->created_at->format('d/m/Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">Aucun pattern MX configuré</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.email-providers.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour à la liste
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
function testEmailDetection(email) {
    document.getElementById('customTestEmail').value = email;
    testCustomEmail();
}

function testCustomEmail() {
    const email = document.getElementById('customTestEmail').value;
    if (!email) {
        alert('Veuillez entrer un email');
        return;
    }
    
    const resultDiv = document.getElementById('testResult');
    const resultPre = document.querySelector('#testResult pre');
    
    // Afficher un loading
    resultDiv.style.display = 'block';
    resultPre.textContent = 'Test en cours...';
    
    fetch('{{ route('admin.email-providers.test') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erreur HTTP: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.result) {
            resultPre.textContent = JSON.stringify(data.result, null, 2);
            
            // Ajouter des couleurs selon le résultat
            if (data.result.valid === false) {
                resultPre.style.backgroundColor = '#fee';
            } else {
                resultPre.style.backgroundColor = '#efe';
            }
        } else {
            resultPre.textContent = JSON.stringify(data, null, 2);
        }
    })
    .catch(error => {
        resultPre.textContent = 'Erreur: ' + error.message;
        resultPre.style.backgroundColor = '#fee';
        console.error('Erreur:', error);
    });
}
</script>
@endpush
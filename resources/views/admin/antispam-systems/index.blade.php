@extends('layouts.admin')

@section('title', 'Gestion des systèmes anti-spam')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Systèmes anti-spam</li>
        </ol>
    </nav>

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-shield-alt text-muted me-2"></i>
            Systèmes anti-spam
        </h1>
        <div>
            <a href="{{ route('admin.antispam-systems.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nouveau système
            </a>
        </div>
    </div>

    @php
        $allSystems = \App\Models\AntispamSystem::withCount('emailAccounts')
            ->orderBy('display_name')
            ->get();
        $totalSystems = $allSystems->count();
        $activeSystems = $allSystems->where('is_active', true)->count();
        $totalAccounts = $allSystems->sum('email_accounts_count');
        $systemsWithPatterns = $allSystems->filter(function($s) {
            return !empty($s->header_patterns) || !empty($s->subject_patterns) || !empty($s->body_patterns);
        })->count();
    @endphp

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                                <i class="fas fa-shield-alt fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total systèmes</h6>
                            <h3 class="mb-0">{{ $totalSystems }}</h3>
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
                            <h3 class="mb-0">{{ $activeSystems }}</h3>
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
                                <i class="fas fa-envelope fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Comptes liés</h6>
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
                            <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                                <i class="fas fa-filter fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Avec patterns</h6>
                            <h3 class="mb-0">{{ $systemsWithPatterns }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-body">
            @if($allSystems->isEmpty())
                <div class="text-center py-5">
                    <i class="fas fa-shield-alt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Aucun système anti-spam configuré</p>
                    <a href="{{ route('admin.antispam-systems.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Ajouter le premier système
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Patterns</th>
                                <th>Comptes</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($allSystems as $system)
                                <tr>
                                    <td>
                                        <strong>{{ $system->display_name }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $system->name }}</small>
                                    </td>
                                    <td>
                                        <small>{{ Str::limit($system->description, 50) }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $patternCount = 0;
                                            if ($system->header_patterns) $patternCount += count($system->header_patterns);
                                            if ($system->subject_patterns) $patternCount += count($system->subject_patterns);
                                            if ($system->body_patterns) $patternCount += count($system->body_patterns);
                                        @endphp
                                        @if($patternCount > 0)
                                            <span class="badge bg-info">{{ $patternCount }} pattern(s)</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($system->email_accounts_count > 0)
                                            <span class="badge bg-primary">
                                                {{ $system->email_accounts_count }} compte(s)
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($system->is_active)
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
                                        <div class="btn-group btn-group-sm" role="group">
                                            {{-- Test Patterns --}}
                                            @if($patternCount > 0)
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    onclick="testPatterns({{ $system->id }})"
                                                    data-bs-toggle="tooltip" 
                                                    title="Tester les patterns">
                                                <i class="fas fa-vial"></i>
                                            </button>
                                            @endif
                                            
                                            {{-- Edit --}}
                                            <a href="{{ route('admin.antispam-systems.edit', $system) }}" 
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
                                                        <form action="{{ route('admin.antispam-systems.destroy', $system) }}" 
                                                              method="POST" 
                                                              data-confirm="Êtes-vous sûr de vouloir supprimer le système {{ $system->display_name }} ?">
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
            @endif
        </div>
    </div>
</div>

<script>
// Initialiser les tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});


function testPatterns(systemId) {
    // TODO: Implémenter le test des patterns
    showToast('info', 'Fonctionnalité de test des patterns à venir');
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
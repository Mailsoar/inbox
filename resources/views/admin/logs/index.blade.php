@extends('layouts.admin')

@section('title', 'Logs Système')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Logs Système</li>
        </ol>
    </nav>

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-file-alt text-muted me-2"></i>
            Logs Système
        </h1>
        <div>
            <button class="btn btn-outline-secondary" 
                    onclick="clearLogs()"
                    data-bs-toggle="tooltip" 
                    title="Vider les logs">
                <i class="fas fa-trash"></i> Nettoyer
            </button>
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
                                <i class="fas fa-file-alt fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Entrées totales</h6>
                            <h3 class="mb-0">{{ number_format($logStats['total']) }}</h3>
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
                                <i class="fas fa-calendar-day fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Aujourd'hui</h6>
                            <h3 class="mb-0">{{ number_format($logStats['today']) }}</h3>
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
                                <i class="fas fa-exclamation-triangle fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Erreurs</h6>
                            <h3 class="mb-0">{{ number_format($logStats['by_level']['error']) }}</h3>
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
                                <i class="fas fa-exclamation-circle fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Avertissements</h6>
                            <h3 class="mb-0">{{ number_format($logStats['by_level']['warning']) }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Niveau</label>
                    <select name="level" class="form-select">
                        <option value="all" {{ $filters['level'] == 'all' ? 'selected' : '' }}>Tous</option>
                        <option value="error" {{ $filters['level'] == 'error' ? 'selected' : '' }}>Erreurs</option>
                        <option value="warning" {{ $filters['level'] == 'warning' ? 'selected' : '' }}>Avertissements</option>
                        <option value="info" {{ $filters['level'] == 'info' ? 'selected' : '' }}>Info</option>
                        <option value="debug" {{ $filters['level'] == 'debug' ? 'selected' : '' }}>Debug</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Date</label>
                    <input type="date" name="date" class="form-control" value="{{ $filters['date'] }}">
                </div>
                
                <div class="col-md-5 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary me-2">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="{{ route('admin.logs.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Recent Errors Alert --}}
    @if(count($logStats['recent_errors']) > 0)
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <div>
            <strong>{{ count($logStats['recent_errors']) }} erreur(s) récente(s) détectée(s) dans les dernières 24 heures.</strong>
            <a href="#recent-errors" class="alert-link ms-2">Voir les détails</a>
        </div>
    </div>
    @endif

    {{-- Recent Errors Card --}}
    @if(count($logStats['recent_errors']) > 0)
    <div class="card shadow-sm border-danger mb-4" id="recent-errors">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Erreurs Récentes (24h)
            </h5>
        </div>
        <div class="card-body">
            <div class="list-group list-group-flush">
                @foreach($logStats['recent_errors'] as $error)
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                {{ $error['timestamp']->format('Y-m-d H:i:s') }}
                                ({{ $error['timestamp']->diffForHumans() }})
                            </small>
                            <p class="mb-0 mt-1">
                                <code>{{ $error['message'] }}</code>
                            </p>
                        </div>
                        <button class="btn btn-sm btn-outline-danger"
                                onclick="viewErrorDetails()"
                                data-bs-toggle="tooltip"
                                title="Voir toutes les erreurs">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Logs Table --}}
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Entrées de Log
                </h5>
                <span class="badge bg-secondary">
                    {{ $logs->count() }} / {{ number_format($totalLines) }} lignes
                </span>
            </div>
        </div>
        <div class="card-body">
            @if($logs->isEmpty())
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Aucun log trouvé avec ces critères</p>
                </div>
            @else
                <div class="logs-container" style="max-height: 600px; overflow-y: auto;">
                    @foreach($logs as $log)
                    <div class="log-entry mb-3 p-3 border rounded 
                         @if($log['level'] == 'error') border-danger bg-danger-subtle
                         @elseif($log['level'] == 'warning') border-warning bg-warning-subtle
                         @else border-light
                         @endif">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge 
                                    @if($log['level'] == 'error') bg-danger
                                    @elseif($log['level'] == 'warning') bg-warning text-dark
                                    @elseif($log['level'] == 'info') bg-info
                                    @elseif($log['level'] == 'debug') bg-secondary
                                    @else bg-light text-dark
                                    @endif">
                                    {{ strtoupper($log['level']) }}
                                </span>
                                <small class="text-muted ms-2">
                                    <i class="fas fa-clock me-1"></i>
                                    {{ $log['timestamp']->format('Y-m-d H:i:s') }}
                                    ({{ $log['timestamp']->diffForHumans() }})
                                </small>
                            </div>
                            <div class="btn-group btn-group-sm" role="group">
                                @if(count($log['stack_trace']) > 0)
                                <button class="btn btn-sm btn-outline-secondary" 
                                        onclick="toggleStackTrace(this)"
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#stack-{{ $loop->index }}"
                                        title="Voir la stack trace">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                @endif
                                <button class="btn btn-sm btn-outline-secondary"
                                        onclick="copyLogEntry('{{ $loop->index }}')"
                                        data-bs-toggle="tooltip"
                                        title="Copier">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="log-message" id="log-message-{{ $loop->index }}">
                            <code>{{ $log['message'] }}</code>
                        </div>
                        
                        @if(count($log['stack_trace']) > 0)
                        <div class="collapse mt-3" id="stack-{{ $loop->index }}">
                            <div class="stack-trace bg-dark text-light p-3 rounded">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-white-50">Stack Trace</small>
                                    <button class="btn btn-sm btn-dark"
                                            onclick="copyStackTrace('{{ $loop->index }}')"
                                            data-bs-toggle="tooltip"
                                            title="Copier la stack trace">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                <pre class="mb-0 small" id="stack-content-{{ $loop->index }}">{{ implode("\n", $log['stack_trace']) }}</pre>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Styles --}}
<style>
.log-entry {
    transition: all 0.2s ease;
}

.log-entry:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.bg-danger-subtle {
    background-color: rgba(220, 53, 69, 0.05) !important;
}

.bg-warning-subtle {
    background-color: rgba(255, 193, 7, 0.05) !important;
}

.log-message code {
    display: block;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 4px;
    font-size: 0.875rem;
    word-break: break-word;
    color: #212529;
}

.stack-trace pre {
    max-height: 300px;
    overflow-y: auto;
    font-size: 0.75rem;
    color: #f8f9fa;
}

.logs-container {
    scrollbar-width: thin;
    scrollbar-color: #dee2e6 #f8f9fa;
}

.logs-container::-webkit-scrollbar {
    width: 8px;
}

.logs-container::-webkit-scrollbar-track {
    background: #f8f9fa;
}

.logs-container::-webkit-scrollbar-thumb {
    background-color: #dee2e6;
    border-radius: 4px;
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

function toggleStackTrace(button) {
    const icon = button.querySelector('i');
    if (icon.classList.contains('fa-chevron-down')) {
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

function clearLogs() {
    if (confirm('Êtes-vous sûr de vouloir vider tous les logs ?')) {
        fetch('{{ route("admin.logs.clear") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            showToast('success', 'Logs vidés avec succès');
            setTimeout(() => window.location.reload(), 1500);
        })
        .catch(error => {
            showToast('danger', 'Erreur lors du vidage des logs');
        });
    }
}


function viewErrorDetails() {
    // Afficher toutes les erreurs
    document.querySelector('[name="level"]').value = 'error';
    document.querySelector('form').submit();
}

function copyLogEntry(index) {
    const message = document.getElementById('log-message-' + index).textContent;
    navigator.clipboard.writeText(message).then(() => {
        showToast('success', 'Message copié dans le presse-papiers');
    });
}

function copyStackTrace(index) {
    const stackTrace = document.getElementById('stack-content-' + index).textContent;
    navigator.clipboard.writeText(stackTrace).then(() => {
        showToast('success', 'Stack trace copiée dans le presse-papiers');
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
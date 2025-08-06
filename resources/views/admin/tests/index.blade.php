@extends('layouts.admin')

@section('title', 'Tests')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Tests</li>
        </ol>
    </nav>

    {{-- Header avec filtre de période --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-flask text-muted me-2"></i>
            Tests de délivrabilité
        </h1>
        <div class="d-flex align-items-center gap-2">
            <form method="GET" id="periodForm" class="d-flex align-items-center gap-2">
                <select name="period" class="form-select" style="width: auto;" onchange="document.getElementById('periodForm').submit()">
                    <option value="1" {{ $period == 1 ? 'selected' : '' }}>Dernières 24h</option>
                    <option value="7" {{ $period == 7 ? 'selected' : '' }}>7 derniers jours</option>
                    <option value="14" {{ $period == 14 ? 'selected' : '' }}>14 derniers jours</option>
                    <option value="30" {{ $period == 30 ? 'selected' : '' }}>30 derniers jours</option>
                    <option value="60" {{ $period == 60 ? 'selected' : '' }}>2 mois</option>
                    <option value="90" {{ $period == 90 ? 'selected' : '' }}>3 mois</option>
                </select>
                @if($period > 7)
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="history" id="historySwitch" value="1" 
                           {{ $showHistory ? 'checked' : '' }} onchange="document.getElementById('periodForm').submit()">
                    <label class="form-check-label" for="historySwitch">
                        <i class="fas fa-history"></i> Historique
                    </label>
                </div>
                @endif
            </form>
        </div>
    </div>
    
    @if($showHistory && isset($historyStats) && $historyStats)
    <div class="alert alert-info mb-3">
        <i class="fas fa-info-circle"></i> 
        Mode historique activé - Données archivées ({{ $period }} derniers jours) :
        {{ number_format($historyStats->total_tests ?? 0) }} tests,
        Inbox moyen: {{ number_format($historyStats->avg_inbox_rate ?? 0, 1) }}%,
        Spam moyen: {{ number_format($historyStats->avg_spam_rate ?? 0, 1) }}%
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="row mb-4">
        @foreach($statusCounts as $status => $count)
        <div class="col">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="@if($status === 'in_progress') bg-warning bg-opacity-10 text-warning
                                        @elseif($status === 'completed') bg-success bg-opacity-10 text-success
                                        @elseif($status === 'cancelled') bg-danger bg-opacity-10 text-danger
                                        @elseif($status === 'timeout') bg-opacity-10 text-white
                                        @else bg-secondary bg-opacity-10 text-secondary
                                        @endif rounded-circle p-2"
                                        @if($status === 'timeout') style="background-color: rgba(255, 87, 34, 0.1); color: #ff5722 !important;" @endif>
                                @if($status === 'in_progress')
                                    <i class="fas fa-clock fa-lg"></i>
                                @elseif($status === 'completed')
                                    <i class="fas fa-check-circle fa-lg"></i>
                                @elseif($status === 'cancelled')
                                    <i class="fas fa-times-circle fa-lg"></i>
                                @elseif($status === 'timeout')
                                    <i class="fas fa-hourglass-end fa-lg"></i>
                                @else
                                    <i class="fas fa-flask fa-lg"></i>
                                @endif
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-2">
                            <small class="text-muted d-block">
                                @if($status === 'timeout')
                                    Timeout
                                @else
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                @endif
                            </small>
                            <h4 class="mb-0">{{ number_format($count) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="row">
        {{-- Graphique des tests --}}
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar text-primary me-2"></i>
                        @if($period == 1)
                            Tests par heure (24 dernières heures)
                        @else
                            Tests par jour ({{ $period }} derniers jours)
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 300px;">
                        <canvas id="testsPerHourChart"></canvas>
                    </div>
                    <div class="mt-3 text-center">
                        <span class="badge bg-secondary me-2">En attente</span>
                        <span class="badge bg-warning text-dark me-2">En cours</span>
                        <span class="badge bg-success me-2">Terminés</span>
                        <span class="badge bg-danger me-2">Annulés</span>
                        <span class="badge bg-danger" style="background-color: #ff5722 !important;">Timeout</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Top visiteurs avec indicateurs de problèmes --}}
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy text-warning me-2"></i>
                        Top 10 Visiteurs 
                        <small class="text-muted">(leads potentiels)</small>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Email</th>
                                    <th class="text-center">Tests</th>
                                    <th>Problèmes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topVisitors as $visitor)
                                <tr class="{{ $visitor['has_issues'] ? 'table-warning' : '' }}">
                                    <td>
                                        <div class="small">
                                            <strong>{{ $visitor['email'] }}</strong>
                                            @if($visitor['spam_rate'] > 30)
                                                <br>
                                                <span class="text-danger">
                                                    <i class="fas fa-exclamation-circle"></i>
                                                    {{ $visitor['spam_rate'] }}% spam
                                                </span>
                                            @endif
                                            @if($visitor['auth_score'] < 70)
                                                <br>
                                                <span class="text-warning">
                                                    <i class="fas fa-shield-alt"></i>
                                                    Auth: {{ $visitor['auth_score'] !== null ? $visitor['auth_score'] . '%' : '-' }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary">{{ $visitor['test_count'] }}</span>
                                    </td>
                                    <td>
                                        @if($visitor['has_issues'])
                                            @if($visitor['issue_type'] === 'spam')
                                                <span class="badge bg-danger" 
                                                      data-bs-toggle="tooltip"
                                                      title="Taux de spam élevé">
                                                    <i class="fas fa-ban"></i> Spam
                                                </span>
                                            @elseif($visitor['issue_type'] === 'auth')
                                                <span class="badge bg-warning text-dark"
                                                      data-bs-toggle="tooltip"
                                                      title="Problèmes d'authentification">
                                                    <i class="fas fa-lock"></i> Auth
                                                </span>
                                            @endif
                                        @else
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> OK
                                            </span>
                                        @endif
                                        @foreach($visitor['audience_types'] as $type)
                                            <span class="badge bg-{{ $type === 'b2b' ? 'info' : 'secondary' }} small">
                                                {{ strtoupper($type) }}
                                            </span>
                                        @endforeach
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">
                                        Aucun visiteur récurrent
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(count($topVisitors) > 0)
                    <div class="alert alert-info mt-3 mb-0">
                        <small>
                            <i class="fas fa-lightbulb"></i>
                            <strong>Leads potentiels :</strong> Les visiteurs avec des problèmes de spam ou d'authentification sont des prospects prioritaires pour vos services.
                        </small>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Filtres --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="period" value="{{ $period }}">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Recherche</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="ID test ou email..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Statut</label>
                    <select name="status" class="form-select">
                        <option value="">Tous</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>En attente</option>
                        <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>En cours</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Terminé</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Annulé</option>
                        <option value="timeout" {{ request('status') == 'timeout' ? 'selected' : '' }}>Timeout</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Du</label>
                    <input type="date" name="date_from" class="form-control" 
                           placeholder="YYYY-MM-DD" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Au</label>
                    <input type="date" name="date_to" class="form-control" 
                           placeholder="YYYY-MM-DD" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary me-2">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="{{ route('admin.tests.index') }}?period={{ $period }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Liste des tests --}}
    <div class="card shadow-sm">
        <div class="card-body">
            @if($tests->isEmpty())
                <div class="text-center py-5">
                    <i class="fas fa-flask fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Aucun test trouvé</p>
                    @if(request()->hasAny(['search', 'status', 'date_from', 'date_to']))
                        <a href="{{ route('admin.tests.index') }}?period={{ $period }}" class="btn btn-primary">
                            <i class="fas fa-times"></i> Réinitialiser les filtres
                        </a>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Test ID</th>
                                <th>Visiteur</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Progression</th>
                                <th>Authentification</th>
                                <th>Créé</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tests as $test)
                            <tr class="{{ $test->has_auth_issues || $test->spam_rate > 30 ? 'table-warning' : '' }}">
                                <td>
                                    <span class="fw-bold">
                                        #{{ $test->unique_id }}
                                    </span>
                                </td>
                                <td>
                                    <div class="small">
                                        {{ $test->visitor_email }}
                                        @if($test->spam_rate > 30)
                                            <br>
                                            <span class="text-danger small">
                                                <i class="fas fa-exclamation-circle"></i>
                                                {{ $test->spam_rate }}% spam
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        {{ strtoupper($test->audience_type) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge 
                                        @if($test->status === 'completed') bg-success
                                        @elseif($test->status === 'in_progress') bg-warning text-dark
                                        @elseif($test->status === 'cancelled') bg-danger
                                        @elseif($test->status === 'timeout') bg-danger
                                        @else bg-secondary @endif"
                                        @if($test->status === 'timeout') style="background-color: #ff5722 !important;" @endif>
                                        @if($test->status === 'timeout')
                                            Timeout
                                        @else
                                            {{ ucfirst(str_replace('_', ' ', $test->status)) }}
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress me-2" style="width: 80px; height: 8px;">
                                            <div class="progress-bar bg-primary" 
                                                 style="width: {{ $test->completion_rate }}%"></div>
                                        </div>
                                        <small class="text-muted">
                                            {{ $test->received_count }}/{{ $test->total_accounts }}
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    @if($test->received_count > 0)
                                        @if($test->has_auth_issues)
                                            <span class="badge bg-warning text-dark"
                                                  data-bs-toggle="tooltip" 
                                                  title="Score d'authentification: {{ $test->auth_score !== null ? $test->auth_score . '%' : 'Pas de données' }}">
                                                <i class="fas fa-shield-alt"></i> {{ $test->auth_score !== null ? $test->auth_score . '%' : '-' }}
                                            </span>
                                        @else
                                            <span class="badge bg-success"
                                                  data-bs-toggle="tooltip" 
                                                  title="Authentification OK">
                                                <i class="fas fa-check-circle"></i> {{ $test->auth_score !== null ? $test->auth_score . '%' : '-' }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">
                                        {{ $test->created_at->format('Y-m-d H:i') }}
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('test.results', $test->unique_id) }}" 
                                           class="btn btn-outline-secondary"
                                           target="_blank"
                                           data-bs-toggle="tooltip" 
                                           title="Vue publique">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        
                                        {{-- Dropdown menu --}}
                                        <div class="btn-group" role="group">
                                            <button type="button" 
                                                    class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" 
                                                    data-bs-toggle="dropdown" 
                                                    aria-expanded="false">
                                                <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                {{-- Option pour forcer la re-vérification --}}
                                                {{-- Options de re-vérification (disponible pour tous sauf cancelled) --}}
                                                @if($test->status !== 'cancelled')
                                                <li>
                                                    <form action="{{ route('admin.tests.force-recheck', $test->id) }}" method="POST" 
                                                          class="recheck-form"
                                                          data-confirm="Êtes-vous sûr de vouloir re-vérifier le test <strong>#{{ $test->unique_id }}</strong> ?<br>Cette action recherchera les emails manquants."
                                                          data-action="Re-vérifier"
                                                          data-btn-class="btn-info">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="fas fa-search-plus me-2 text-info"></i> Re-vérifier (chercher manquants)
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form action="{{ route('admin.tests.force-recheck', $test->id) }}?clear=1" method="POST"
                                                          class="recheck-form"
                                                          data-confirm="Êtes-vous sûr de vouloir effectuer une re-vérification complète du test <strong>#{{ $test->unique_id }}</strong> ?<br><strong class='text-danger'>⚠️ Cela effacera tous les résultats existants.</strong>"
                                                          data-action="Re-vérifier complètement"
                                                          data-btn-class="btn-warning">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="fas fa-redo me-2 text-warning"></i> Re-vérification complète
                                                        </button>
                                                    </form>
                                                </li>
                                                @endif
                                                
                                                {{-- Option pour annuler un test pending --}}
                                                @if($test->status === 'pending')
                                                <li>
                                                    <form action="{{ route('admin.tests.cancel', $test->id) }}" method="POST">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="fas fa-ban me-2 text-warning"></i> Annuler le test
                                                        </button>
                                                    </form>
                                                </li>
                                                @endif
                                                
                                                {{-- Séparateur si nécessaire --}}
                                                @if($test->status !== 'cancelled' || $test->status === 'pending')
                                                <li><hr class="dropdown-divider"></li>
                                                @endif
                                                
                                                {{-- Option supprimer (toujours disponible) --}}
                                                <li>
                                                    <form action="{{ route('admin.tests.destroy', $test->id) }}" 
                                                          method="POST" 
                                                          data-confirm="Êtes-vous sûr de vouloir supprimer le test <strong>#{{ $test->unique_id }}</strong> ?<br>Cette action est irréversible."
                                                          data-action="Supprimer"
                                                          data-btn-class="btn-danger">
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
                @if($tests->hasPages())
                    <div class="mt-3">
                        {{ $tests->withQueryString()->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Initialiser les tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Graphique des tests
const testsPerHourData = @json($testsPerHour);

// Préparer les données pour le graphique
const labels = testsPerHourData.map(d => d.label);
const totalData = testsPerHourData.map(d => d.total);
const pendingData = testsPerHourData.map(d => d.pending_percent);
const progressData = testsPerHourData.map(d => d.progress_percent);
const completedData = testsPerHourData.map(d => d.completed_percent);
const cancelledData = testsPerHourData.map(d => d.cancelled_percent);
const timeoutData = testsPerHourData.map(d => d.timeout_percent || 0);

const ctx = document.getElementById('testsPerHourChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Total tests',
                data: totalData,
                backgroundColor: 'rgba(13, 110, 253, 0.8)',
                borderColor: '#0d6efd',
                borderWidth: 1,
                yAxisID: 'y-total'
            },
            {
                label: '% En attente',
                data: pendingData,
                backgroundColor: 'rgba(108, 117, 125, 0.6)',
                type: 'line',
                borderColor: '#6c757d',
                tension: 0.4,
                yAxisID: 'y-percentage',
                fill: false
            },
            {
                label: '% En cours',
                data: progressData,
                backgroundColor: 'rgba(255, 193, 7, 0.6)',
                type: 'line',
                borderColor: '#ffc107',
                tension: 0.4,
                yAxisID: 'y-percentage',
                fill: false
            },
            {
                label: '% Terminés',
                data: completedData,
                backgroundColor: 'rgba(25, 135, 84, 0.6)',
                type: 'line',
                borderColor: '#198754',
                tension: 0.4,
                yAxisID: 'y-percentage',
                fill: false
            },
            {
                label: '% Annulés',
                data: cancelledData,
                backgroundColor: 'rgba(220, 53, 69, 0.6)',
                type: 'line',
                borderColor: '#dc3545',
                tension: 0.4,
                yAxisID: 'y-percentage',
                fill: false
            },
            {
                label: '% Timeout',
                data: timeoutData,
                backgroundColor: 'rgba(255, 87, 34, 0.6)',
                type: 'line',
                borderColor: '#ff5722',
                borderWidth: 2,
                borderDash: [5, 5],
                tension: 0.4,
                yAxisID: 'y-percentage',
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.datasetIndex === 0) {
                            label += context.parsed.y + ' tests';
                        } else {
                            label += context.parsed.y.toFixed(1) + '%';
                        }
                        return label;
                    }
                }
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                }
            },
            'y-total': {
                type: 'linear',
                display: true,
                position: 'left',
                beginAtZero: true,
                ticks: {
                    precision: 0
                },
                title: {
                    display: true,
                    text: 'Nombre de tests'
                }
            },
            'y-percentage': {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                },
                title: {
                    display: true,
                    text: 'Pourcentage par statut'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Toast notifications
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

// Auto-refresh pour les tests en cours
@if($tests->where('status', 'in_progress')->count() > 0)
setTimeout(() => {
    window.location.reload();
}, 30000); // Rafraîchir toutes les 30 secondes
@endif

// Gestion des formulaires de re-vérification avec loader
document.addEventListener('DOMContentLoaded', function() {
    // Intercepter les formulaires de re-vérification
    document.querySelectorAll('.recheck-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const message = form.getAttribute('data-confirm');
            const action = form.getAttribute('data-action') || 'Confirmer';
            const btnClass = form.getAttribute('data-btn-class') || 'btn-primary';
            
            // Utiliser la modal de confirmation
            showConfirmModal(
                message,
                function() {
                    // Afficher le loader
                    const overlay = document.getElementById('loadingOverlay');
                    if (overlay) {
                        overlay.classList.remove('d-none');
                        overlay.style.display = 'flex';
                    }
                    
                    // Soumettre le formulaire
                    form.submit();
                },
                action,
                btnClass
            );
        });
    });
});
</script>
{{-- Loader Overlay --}}
<div id="loadingOverlay" class="d-none" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
    <div class="text-center">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Traitement en cours...</span>
        </div>
        <div class="mt-3 text-white">
            <h5>Traitement en cours...</h5>
            <p class="mb-0">Veuillez patienter pendant la re-vérification.</p>
        </div>
    </div>
</div>
@endsection
@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
        </ol>
    </nav>

    {{-- Header avec filtre de période --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-tachometer-alt text-muted me-2"></i>
            Dashboard
        </h1>
        <form method="GET" id="periodForm">
            <select name="period" class="form-select" style="width: auto;" onchange="document.getElementById('periodForm').submit()">
                <option value="1" {{ $period == '1' ? 'selected' : '' }}>Dernières 24h</option>
                <option value="7" {{ $period == '7' ? 'selected' : '' }}>7 derniers jours</option>
                <option value="14" {{ $period == '14' ? 'selected' : '' }}>14 derniers jours</option>
                <option value="30" {{ $period == '30' ? 'selected' : '' }}>30 derniers jours</option>
                <option value="60" {{ $period == '60' ? 'selected' : '' }}>2 mois</option>
                <option value="90" {{ $period == '90' ? 'selected' : '' }}>3 mois</option>
                <option value="180" {{ $period == '180' ? 'selected' : '' }}>6 mois</option>
            </select>
        </form>
    </div>

    {{-- Zone d'alertes système --}}
    @if(count($systemAlerts['critical']) > 0 || count($systemAlerts['warning']) > 0)
    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <div>
            <strong>{{ count($systemAlerts['critical']) + count($systemAlerts['warning']) }} alerte(s) système détectée(s).</strong>
            <a href="#system-alerts" class="alert-link ms-2">Voir les détails</a>
        </div>
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                                <i class="fas fa-clock fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Tests actifs</h6>
                            <h3 class="mb-0">{{ $systemStats['active_tests'] }}</h3>
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
                            <h6 class="text-muted mb-1">Échecs (24h)</h6>
                            <h3 class="mb-0">{{ $systemStats['failed_tests_24h'] }}</h3>
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
                                <i class="fas fa-at fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Comptes actifs</h6>
                            <h3 class="mb-0">{{ $systemStats['active_accounts'] }}/{{ $systemStats['total_accounts'] }}</h3>
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
                            <h6 class="text-muted mb-1">Emails (7j)</h6>
                            <h3 class="mb-0">{{ number_format($systemStats['total_emails_7d']) }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Alertes Système Détaillées --}}
    @if(count($systemAlerts['critical']) > 0 || count($systemAlerts['warning']) > 0)
    <div class="card shadow-sm mb-4" id="system-alerts">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Alertes Système
                <span class="badge bg-dark ms-2">{{ count($systemAlerts['critical']) + count($systemAlerts['warning']) }}</span>
            </h5>
        </div>
        <div class="card-body">
            <div class="alerts-container" style="max-height: 300px; overflow-y: auto;">
                {{-- Alertes critiques --}}
                @foreach($systemAlerts['critical'] as $alert)
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fas fa-times-circle me-3 fa-lg"></i>
                    <div class="flex-grow-1">
                        <strong>{{ $alert['message'] }}</strong>
                        @if($alert['type'] === 'token_expired' || $alert['type'] === 'connection_failed')
                            <a href="{{ route('admin.email-accounts.edit', $alert['account_id']) }}" 
                               class="ms-3 btn btn-sm btn-light">
                                <i class="fas fa-wrench me-1"></i> Corriger
                            </a>
                        @endif
                    </div>
                    <small class="fw-bold">{{ ucfirst($alert['provider'] ?? 'système') }}</small>
                </div>
                @endforeach
                
                {{-- Avertissements --}}
                @foreach($systemAlerts['warning'] as $alert)
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-circle me-3 fa-lg"></i>
                    <div class="flex-grow-1">
                        <strong>{{ $alert['message'] }}</strong>
                        @if($alert['type'] === 'token_expiring')
                            <a href="{{ route('admin.email-accounts.edit', $alert['account_id']) }}" 
                               class="ms-3 btn btn-sm btn-outline-dark">
                                Rafraîchir
                            </a>
                        @endif
                    </div>
                    @if(isset($alert['provider']))
                        <small>{{ ucfirst($alert['provider']) }}</small>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        {{-- Monitoring des comptes email --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-heartbeat text-danger me-2"></i>
                            Santé des Comptes Email
                        </h5>
                        <a href="{{ route('admin.email-accounts.index') }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-cog"></i> Gérer
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Compte</th>
                                    <th>Provider</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($emailAccountsHealth as $health)
                                <tr class="@if($health['status'] === 'critical') table-danger @elseif($health['status'] === 'warning') table-warning @endif">
                                    <td>
                                        <small class="fw-semibold">{{ $health['account']->email }}</small>
                                        @if($health['stats']['last_activity'])
                                            <br><small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                {{ $health['stats']['last_activity']->diffForHumans() }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            {{ ucfirst($health['account']->provider) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($health['status'] === 'critical')
                                            <span class="badge bg-danger">Critique</span>
                                        @elseif($health['status'] === 'warning')
                                            <span class="badge bg-warning text-dark">Attention</span>
                                        @elseif($health['status'] === 'inactive')
                                            <span class="badge bg-secondary">Inactif</span>
                                        @else
                                            <span class="badge bg-success">OK</span>
                                        @endif
                                        @if(count($health['issues']) > 0)
                                            <i class="fas fa-info-circle ms-1" 
                                               data-bs-toggle="tooltip" 
                                               title="{{ implode(', ', $health['issues']) }}"></i>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" 
                                                    class="btn btn-outline-primary"
                                                    onclick="testConnection({{ $health['account']->id }})"
                                                    data-bs-toggle="tooltip"
                                                    title="Tester">
                                                <i class="fas fa-plug"></i>
                                            </button>
                                            <a href="{{ route('admin.email-accounts.edit', $health['account']->id) }}" 
                                               class="btn btn-outline-secondary"
                                               data-bs-toggle="tooltip"
                                               title="Éditer">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Monitoring des filtres anti-spam --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt text-primary me-2"></i>
                        Couverture des Filtres Anti-Spam
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Filtre</th>
                                    <th>Statut</th>
                                    <th>Couverture</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($spamFiltersHealth as $filter)
                                <tr class="@if($filter['status'] === 'critical') table-danger @elseif($filter['status'] === 'warning') table-warning @endif">
                                    <td>
                                        <strong>{{ $filter['name'] }}</strong>
                                    </td>
                                    <td>
                                        @if($filter['status'] === 'critical')
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle"></i> Critique
                                            </span>
                                        @elseif($filter['status'] === 'warning')
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-exclamation-circle"></i> Attention
                                            </span>
                                        @elseif($filter['status'] === 'info')
                                            <span class="badge bg-info">
                                                <i class="fas fa-info-circle"></i> Info
                                            </span>
                                        @else
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> OK
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ $filter['message'] }}</small>
                                        @if($filter['coverage'] > 0)
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-chart-bar me-1"></i>
                                                {{ $filter['coverage'] }} tests
                                            </small>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Basé sur les 7 derniers jours
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Graphique des tests --}}
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        Évolution des Tests 
                        @if($period == '1')
                            <small class="text-muted">(Par heure - 24h)</small>
                        @elseif($period == '7')
                            <small class="text-muted">(Par jour - 7j)</small>
                        @elseif($period == '14')
                            <small class="text-muted">(Par jour - 14j)</small>
                        @elseif($period == '30')
                            <small class="text-muted">(Par jour - 30j)</small>
                        @elseif($period == '60')
                            <small class="text-muted">(Par jour - 2 mois)</small>
                        @elseif($period == '90')
                            <small class="text-muted">(Par jour - 3 mois)</small>
                        @elseif($period == '180')
                            <small class="text-muted">(Par jour - 6 mois)</small>
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 250px;">
                        <canvas id="testsChart"></canvas>
                    </div>
                    <div class="mt-3 text-center">
                        <span class="badge bg-primary me-2">Volume total</span>
                        <span class="badge bg-warning text-dark me-2">% Tests annulés</span>
                        <span class="badge bg-danger me-2">% Tests timeout</span>
                        <span class="badge bg-success">% Tests terminés</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tests problématiques récents --}}
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            Tests Problématiques
                        </h5>
                        <a href="{{ route('admin.tests.index') }}?status=failed" class="btn btn-sm btn-outline-danger">
                            Voir tout
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @forelse($problematicTests as $item)
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold small">
                                        <a href="{{ route('admin.tests.show', $item['test']->id) }}" 
                                           class="text-decoration-none">
                                            #{{ $item['test']->unique_id }}
                                        </a>
                                    </div>
                                    <small class="text-muted">
                                        {{ $item['test']->visitor_email }}
                                    </small>
                                </div>
                                <span class="badge bg-danger">
                                    {{ $item['issue'] }}
                                </span>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                {{ $item['test']->created_at->diffForHumans() }}
                            </small>
                        </div>
                        @empty
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                            <p class="mb-0">Aucun test problématique</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Lien vers les logs --}}
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h5 class="mb-3">
                        <i class="fas fa-file-alt text-info me-2"></i>
                        Besoin de plus de détails ?
                    </h5>
                    <p class="text-muted mb-3">
                        Consultez les logs détaillés pour analyser les erreurs système, 
                        les problèmes de connexion et les échecs de parsing.
                    </p>
                    <a href="{{ route('admin.logs.index') }}" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>
                        Consulter les Logs Détaillés
                    </a>
                </div>
            </div>
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

// Données pour les graphiques
const testsTrend = @json($testsTrend);

// Graphique évolution des tests
const testsCtx = document.getElementById('testsChart').getContext('2d');
new Chart(testsCtx, {
    type: 'line',
    data: {
        labels: testsTrend.map(d => {
            if (d.datetime) {
                if (d.date.includes(':')) {
                    return d.date;
                } else {
                    return d.date;
                }
            }
            return d.date;
        }),
        datasets: [
            {
                label: 'Volume total',
                data: testsTrend.map(d => d.total),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: false,
                yAxisID: 'y-volume'
            },
            {
                label: '% Tests annulés',
                data: testsTrend.map(d => d.cancelled_percent),
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                tension: 0.4,
                fill: false,
                yAxisID: 'y-percentage'
            },
            {
                label: '% Tests timeout',
                data: testsTrend.map(d => d.failed_percent),
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4,
                fill: false,
                yAxisID: 'y-percentage'
            },
            {
                label: '% Tests terminés',
                data: testsTrend.map(d => d.completed_percent || 0),
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4,
                fill: false,
                yAxisID: 'y-percentage'
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
            'y-volume': {
                type: 'linear',
                display: true,
                position: 'left',
                beginAtZero: true,
                ticks: {
                    precision: 0
                },
                title: {
                    display: true,
                    text: 'Volume de tests'
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
                    text: 'Pourcentage'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
});

// Test de connexion
function testConnection(accountId) {
    const button = event.target.closest('button');
    const originalHtml = button.innerHTML;
    
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch(`/admin/email-accounts/${accountId}/test`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        showToast(data.success ? 'success' : 'danger', data.message || 'Erreur lors du test');
        if (data.success) {
            setTimeout(() => window.location.reload(), 2000);
        }
    })
    .catch(error => {
        showToast('danger', 'Erreur lors du test de connexion');
    })
    .finally(() => {
        button.disabled = false;
        button.innerHTML = originalHtml;
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

// Auto-refresh si des tests sont actifs
@if($systemStats['active_tests'] > 0)
setTimeout(() => {
    window.location.reload();
}, 60000); // Rafraîchir toutes les minutes
@endif
</script>
@endsection
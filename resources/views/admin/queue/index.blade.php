@extends('layouts.admin')

@section('title', 'File d\'attente')

@section('page-title', 'Gestion de la file d\'attente')
@section('page-subtitle', 'Surveillance et gestion des jobs de traitement d\'emails')

@section('content')
<div class="container-fluid">
    {{-- Section d'aide --}}
    <div class="alert alert-info mb-4">
        <h5 class="alert-heading">
            <i class="fas fa-info-circle me-2"></i>À propos de cette page
        </h5>
        <p class="mb-2">
            Cette page surveille les <strong>ProcessEmailAddressJob</strong> - un job par compte email qui vérifie les emails pour tous les tests actifs.
        </p>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <h6 class="fw-bold">Comment ça marche ?</h6>
                <ul class="small mb-0">
                    <li><strong>1 job = 1 compte email</strong> (ex: pierre@gmail.com)</li>
                    <li>Chaque job vérifie tous les tests actifs pour ce compte</li>
                    <li>Intervalles intelligents : 1min → 5min → 15min</li>
                    <li><strong>Protection anti-doublons</strong> : 1 seul job par compte</li>
                    <li>Verrou de 5 minutes pendant l'exécution (ShouldBeUnique)</li>
                    <li>Retry automatique en cas d'échec (3 tentatives)</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold">Traitement automatique :</h6>
                <ul class="small mb-0">
                    <li><strong>✅ Automatisé :</strong> Cron exécute chaque minute</li>
                    <li><strong>🔄 Process optimized :</strong> Crée les jobs nécessaires</li>
                    <li><strong>⚙️ Process addresses :</strong> Traite les jobs en queue</li>
                    <li><strong>📊 Monitoring :</strong> Cette page montre l'état en temps réel</li>
                </ul>
            </div>
        </div>
        <div class="mt-3 p-2 bg-light rounded">
            <strong>Configuration actuelle du cron (chaque minute) :</strong><br>
            <code class="small">* * * * * cd /home/pipi9999/inbox.mailsoar.com && /usr/local/bin/php -f artisan schedule:run</code>
        </div>
    </div>

    {{-- Cartes de statut --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card primary">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="ms-3 flex-grow-1">
                        <div class="stat-label">Jobs en attente</div>
                        <div class="stat-value">
                            {{ $pendingJobs->sum('count') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card danger">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="ms-3 flex-grow-1">
                        <div class="stat-label">Jobs échoués</div>
                        <div class="stat-value">
                            {{ $failedJobs->sum('count') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="ms-3 flex-grow-1">
                        <div class="stat-label">Queues actives</div>
                        <div class="stat-value">
                            {{ $pendingJobs->count() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-redo"></i>
                    </div>
                    <div class="ms-3 flex-grow-1">
                        <div class="stat-label">À relancer</div>
                        <div class="stat-value">
                            {{ $recentFailedJobs->count() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Actions principales --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-tools me-2"></i>Actions disponibles
            </h5>
        </div>
        <div class="card-body">
            <div class="d-flex gap-2 flex-wrap">
                <form action="{{ route('admin.queue.process') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-admin btn-primary">
                        <i class="fas fa-play me-2"></i>Traiter la queue (30s)
                    </button>
                </form>
                
                @if(!$pendingJobs->isEmpty())
                <div class="dropdown">
                    <button class="btn btn-admin btn-warning dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-broom me-2"></i>Vider une queue
                    </button>
                    <ul class="dropdown-menu">
                        @foreach($pendingJobs as $queue)
                        <li>
                            <form action="{{ route('admin.queue.clear', $queue->queue) }}" method="POST" 
                                  data-confirm="Êtes-vous sûr de vouloir vider la queue <strong>{{ $queue->queue }}</strong> ?<br>Cette action supprimera <strong>{{ $queue->count }}</strong> job(s) en attente.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="dropdown-item">
                                    <i class="fas fa-layer-group me-2"></i>
                                    {{ $queue->queue }} 
                                    <span class="badge bg-secondary ms-2">{{ $queue->count }}</span>
                                </button>
                            </form>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
                
                @if(!$failedJobs->isEmpty())
                <form action="{{ route('admin.queue.clear-failed') }}" method="POST" class="d-inline"
                      data-confirm="Êtes-vous sûr de vouloir supprimer tous les jobs échoués ?<br>Cette action est irréversible.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-admin btn-danger">
                        <i class="fas fa-trash me-2"></i>Supprimer tous les échecs
                    </button>
                </form>
                @endif
                
                <a href="{{ route('admin.queue.index') }}" class="btn btn-admin btn-outline-secondary">
                    <i class="fas fa-sync me-2"></i>Rafraîchir
                </a>
            </div>
        </div>
    </div>

    {{-- Jobs récents en attente --}}
    @if(!$recentJobs->isEmpty())
    <div class="card table-admin mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-history me-2"></i>Jobs récents en attente
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th width="60">ID</th>
                        <th width="120">Queue</th>
                        <th>Type de Job</th>
                        <th width="250">Compte Email</th>
                        <th width="100" class="text-center">Tentatives</th>
                        <th width="150">Créé</th>
                        <th width="150">Disponible</th>
                        <th width="80" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentJobs as $job)
                    <tr>
                        <td class="text-muted">#{{ $job['id'] }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ $job['queue'] }}</span>
                        </td>
                        <td>
                            @if(str_contains($job['job_name'], 'ProcessEmailAddressJob'))
                                <span class="badge bg-success">ProcessEmailAddress</span>
                            @else
                                <small class="text-muted">{{ $job['job_name'] }}</small>
                            @endif
                        </td>
                        <td>
                            @if($job['test_id'])
                                <i class="fas fa-envelope text-primary me-1"></i>
                                <strong>{{ $job['test_id'] }}</strong>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $job['attempts'] > 1 ? 'bg-warning' : ($job['attempts'] == 1 ? 'bg-info' : 'bg-light text-dark') }}">
                                {{ $job['attempts'] }}
                            </span>
                        </td>
                        <td>
                            <small>{{ $job['created_at']->diffForHumans() }}</small>
                        </td>
                        <td>
                            @if($job['available_at']->isPast())
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i>Prêt
                                </span>
                            @else
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>{{ $job['available_at']->diffForHumans() }}
                                </small>
                            @endif
                        </td>
                        <td class="text-center">
                            <form action="{{ route('admin.queue.cancel', $job['id']) }}" method="POST" class="d-inline"
                                  data-confirm="Êtes-vous sûr de vouloir annuler ce job ?<br><strong>Compte:</strong> {{ $job['test_id'] ?? 'N/A' }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Annuler ce job">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="card mb-4">
        <div class="card-body text-center py-5">
            <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
            <h5 class="mt-3">Aucun job en attente</h5>
            <p class="text-muted">Toutes les recherches ont été traitées.</p>
        </div>
    </div>
    @endif

    {{-- Jobs échoués récents --}}
    @if(!$recentFailedJobs->isEmpty())
    <div class="card table-admin border-danger">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">
                <i class="fas fa-exclamation-circle me-2"></i>Jobs échoués récents
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th width="60">ID</th>
                        <th width="120">Queue</th>
                        <th>Type de Job</th>
                        <th width="250">Compte Email</th>
                        <th width="150">Échoué</th>
                        <th>Erreur</th>
                        <th width="120" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentFailedJobs as $job)
                    <tr>
                        <td class="text-muted">#{{ $job['id'] }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ $job['queue'] }}</span>
                        </td>
                        <td>
                            @if(str_contains($job['job_name'], 'ProcessEmailAddressJob'))
                                <span class="badge bg-success">ProcessEmailAddress</span>
                            @else
                                <small class="text-muted">{{ $job['job_name'] }}</small>
                            @endif
                        </td>
                        <td>
                            @if($job['test_id'])
                                <i class="fas fa-envelope text-primary me-1"></i>
                                <strong>{{ $job['test_id'] }}</strong>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <small>{{ \Carbon\Carbon::parse($job['failed_at'])->diffForHumans() }}</small>
                        </td>
                        <td>
                            <small class="text-danger text-truncate d-inline-block" style="max-width: 300px;" 
                                   title="{{ $job['exception'] }}">
                                {{ Str::limit($job['exception'], 100) }}
                            </small>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <form action="{{ route('admin.queue.retry', $job['id']) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-warning" title="Relancer ce job">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.queue.delete-failed', $job['id']) }}" method="POST" class="d-inline"
                                      data-confirm="Êtes-vous sûr de vouloir supprimer ce job échoué ?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Informations supplémentaires --}}
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-terminal me-2"></i>Commandes CLI utiles
                    </h6>
                </div>
                <div class="card-body">
                    <pre class="mb-0"><code># Traiter la queue manuellement (30 secondes)
php artisan emails:process-addresses --timeout=30

# Vérifier l'état du scheduler
php artisan schedule:list

# Relancer tous les jobs échoués
php artisan queue:retry all

# Voir les logs en temps réel
tail -f storage/logs/email-queue-processing.log</code></pre>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-flow-chart me-2"></i>Flux de traitement
                    </h6>
                </div>
                <div class="card-body">
                    <ol class="small mb-0">
                        <li class="mb-2">
                            <strong>Nouveau test créé</strong> → Status: pending
                        </li>
                        <li class="mb-2">
                            <strong>emails:process-optimized</strong> (chaque minute)<br>
                            → Crée un job par compte email actif
                        </li>
                        <li class="mb-2">
                            <strong>ProcessEmailAddressJob</strong> dans la queue<br>
                            → Vérifie tous les tests pour ce compte
                        </li>
                        <li class="mb-2">
                            <strong>emails:process-addresses</strong> (chaque minute)<br>
                            → Traite les jobs pendant 50 secondes
                        </li>
                        <li>
                            <strong>Résultat</strong> → Email trouvé ou timeout après 30min
                        </li>
                    </ol>
                    <hr class="my-2">
                    <div class="text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Performance moyenne:</strong><br>
                        Gmail/Outlook: 5-15 sec | Yahoo: 30-60 sec
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Auto-refresh toutes les 30 secondes si des jobs sont en attente
@if($pendingJobs->sum('count') > 0)
setTimeout(function() {
    window.location.reload();
}, 30000);
@endif
</script>
@endpush
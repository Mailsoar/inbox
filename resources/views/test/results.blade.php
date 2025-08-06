@extends('layouts.app')

@section('content')
@php
    // Calculer les statistiques
    $totalEmails = $test->emailAccounts->count();
    $receivedEmails = $test->receivedEmails->count();
    $notReceivedCount = $totalEmails - $receivedEmails;
    
    // Utiliser la méthode centralisée du modèle Test pour obtenir les placements inbox
    $inboxPlacements = \App\Models\Test::getInboxPlacements();
    $additionalInboxPlacements = array_diff($inboxPlacements, ['inbox']); // Tous sauf 'inbox' lui-même
    
    // Compter par placement
    $placementCounts = $test->receivedEmails->groupBy('placement')->map->count();
    $inboxCount = $placementCounts->get('inbox', 0);
    $spamCount = $placementCounts->get('spam', 0);
    $promotionsCount = $placementCounts->get('promotions', 0);
    $updatesCount = $placementCounts->get('updates', 0);
    $socialCount = $placementCounts->get('social', 0);
    $forumsCount = $placementCounts->get('forums', 0);
    
    // Calculer le total inbox en incluant tous les placements de type inbox ou additional_inbox
    $inboxTotalCount = 0;
    foreach ($inboxPlacements as $placement) {
        $inboxTotalCount += $placementCounts->get($placement, 0);
    }
    
    // Calculer les pourcentages
    $inboxRate = $totalEmails > 0 ? round(($inboxTotalCount / $totalEmails) * 100) : 0;
    $spamRate = $totalEmails > 0 ? round(($spamCount / $totalEmails) * 100) : 0;
    $promotionsRate = $totalEmails > 0 ? round(($promotionsCount / $totalEmails) * 100) : 0;
    $notReceivedRate = $totalEmails > 0 ? round(($notReceivedCount / $totalEmails) * 100) : 0;
    
    // Statistiques d'authentification
    $spfPass = $test->receivedEmails->where('spf_result', 'pass')->count();
    $dkimPass = $test->receivedEmails->where('dkim_result', 'pass')->count();
    $dmarcPass = $test->receivedEmails->where('dmarc_result', 'pass')->count();
    
    // Statistiques des filtres anti-spam par placement - utiliser display_name
    $antispamStats = [];
    
    // Récupérer tous les systèmes antispam disponibles
    $allAntispamSystems = \App\Models\AntispamSystem::all()->keyBy('name');
    
    // Collecter les systèmes antispam associés aux comptes de ce test
    $testAntispamSystems = [];
    foreach ($test->emailAccounts as $account) {
        if ($account->antispamSystems) {
            foreach ($account->antispamSystems as $system) {
                if (!isset($testAntispamSystems[$system->name])) {
                    $testAntispamSystems[$system->name] = $system;
                }
            }
        }
    }
    
    // Aussi collecter les systèmes détectés dans spam_filters_detected
    foreach ($test->receivedEmails as $email) {
        if ($email->spam_filters_detected) {
            $filters = is_string($email->spam_filters_detected) ? 
                json_decode($email->spam_filters_detected, true) : 
                $email->spam_filters_detected;
            
            if (is_array($filters)) {
                foreach ($filters as $filterName => $filterData) {
                    if (is_array($filterData) && isset($filterData['detected']) && $filterData['detected']) {
                        $filterName = is_numeric($filterName) ? $filterData : $filterName;
                    } elseif (!is_array($filterData)) {
                        $filterName = $filterData;
                    }
                    
                    // Vérifier si ce système existe dans la base
                    if (isset($allAntispamSystems[$filterName]) && !isset($testAntispamSystems[$filterName])) {
                        $testAntispamSystems[$filterName] = $allAntispamSystems[$filterName];
                    }
                }
            }
        }
    }
    
    // Maintenant calculer les stats pour chaque système antispam utilisant display_name
    foreach ($testAntispamSystems as $systemName => $system) {
        $displayName = $system->display_name;
        $antispamStats[$displayName] = [
            'inbox' => 0,
            'spam' => 0,
            'timeout' => 0
        ];
        
        // Parcourir tous les comptes pour ce système
        foreach ($test->emailAccounts as $account) {
            $receivedEmail = $test->receivedEmails->where('email_account_id', $account->id)->first();
            $hasThisSystem = false;
            
            // Vérifier si l'email a ce système dans spam_filters_detected
            if ($receivedEmail && $receivedEmail->spam_filters_detected) {
                $filters = is_string($receivedEmail->spam_filters_detected) ? 
                    json_decode($receivedEmail->spam_filters_detected, true) : 
                    $receivedEmail->spam_filters_detected;
                
                if (is_array($filters)) {
                    foreach ($filters as $filterName => $filterData) {
                        if (is_array($filterData) && isset($filterData['detected']) && $filterData['detected']) {
                            $filterName = is_numeric($filterName) ? $filterData : $filterName;
                        } elseif (!is_array($filterData)) {
                            $filterName = $filterData;
                        }
                        
                        if ($filterName === $systemName) {
                            $hasThisSystem = true;
                            break;
                        }
                    }
                }
            }
            
            // Ou vérifier si le compte a ce système associé
            if (!$hasThisSystem && $account->antispamSystems) {
                $hasThisSystem = $account->antispamSystems->where('name', $systemName)->count() > 0;
            }
            
            if ($hasThisSystem) {
                if ($receivedEmail) {
                    // Email reçu - détecter le placement
                    if (in_array($receivedEmail->placement, $inboxPlacements)) {
                        $antispamStats[$displayName]['inbox']++;
                    } elseif ($receivedEmail->placement === 'spam') {
                        $antispamStats[$displayName]['spam']++;
                    }
                } else {
                    // Email non reçu
                    $antispamStats[$displayName]['timeout']++;
                }
            }
        }
    }
    
    // Trier par total décroissant
    uasort($antispamStats, function($a, $b) {
        $totalA = $a['inbox'] + $a['spam'] + $a['timeout'];
        $totalB = $b['inbox'] + $b['spam'] + $b['timeout'];
        return $totalB - $totalA;
    });
    
    
    // Grouper par vrai fournisseur
    $providerStats = [];
    foreach ($test->emailAccounts as $account) {
        $realProvider = $account->getRealProvider();
        
        if (!isset($providerStats[$realProvider])) {
            $providerStats[$realProvider] = [
                'accounts' => collect(),
                'total' => 0,
                'received' => 0,
                'inbox' => 0,
                'spam' => 0,
                'promotions' => 0,
            ];
        }
        
        $providerStats[$realProvider]['accounts']->push($account);
        $providerStats[$realProvider]['total']++;
    }
    
    // Calculer les stats pour chaque fournisseur
    foreach ($providerStats as $provider => &$stats) {
        $accountIds = $stats['accounts']->pluck('id');
        $providerEmails = $test->receivedEmails->whereIn('email_account_id', $accountIds);
        
        $stats['received'] = $providerEmails->count();
        
        // Calculer inbox total
        $providerInboxCount = 0;
        foreach ($inboxPlacements as $placement) {
            $providerInboxCount += $providerEmails->where('placement', $placement)->count();
        }
        
        $stats['inbox'] = $providerInboxCount;
        $stats['spam'] = $providerEmails->where('placement', 'spam')->count();
        // Supprimer le comptage séparé de promotions car c'est inclus dans inbox
        $stats['timeout'] = $stats['total'] - $stats['received'];
        
        unset($stats['accounts']); // Nettoyer pour la vue
    }
@endphp

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- En-tête avec progression -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    @if($test->status === 'pending' || $test->status === 'in_progress')
                        @php
                            $timeoutMinutes = config('mailsoar.email_check_timeout_minutes', 30);
                            $minutesElapsed = $test->created_at->diffInMinutes(now());
                            $minutesRemaining = $timeoutMinutes - $minutesElapsed;
                        @endphp
                        
                        @if($minutesRemaining <= 5 && $minutesRemaining > 0)
                            <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                {!! __('messages.results.test_expiring_warning', ['minutes' => $minutesRemaining]) !!}
                                {{ __('messages.results.unreceived_marked_undelivered') }}
                            </div>
                        @endif
                    @endif
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="mb-1">{{ __('messages.results.title') }} <span class="text-primary">#{{ $test->unique_id }}</span></h3>
                            <p class="text-muted mb-0">
                                <i class="fas fa-clock"></i> {{ __('messages.results.created') }} {{ $test->created_at->diffForHumans() }}
                                @if($test->status === 'completed')
                                    <span class="badge bg-success ms-2">{{ __('messages.results.status_completed') }}</span>
                                @elseif($test->status === 'in_progress')
                                    <span class="badge bg-warning ms-2">{{ __('messages.results.status_in_progress') }}</span>
                                @elseif($test->status === 'timeout')
                                    <span class="badge bg-warning ms-2">{{ __('messages.results.status_timeout') }}</span>
                                @elseif($test->status === 'cancelled')
                                    <span class="badge bg-danger ms-2">{{ __('messages.results.status_cancelled') }}</span>
                                @else
                                    <span class="badge bg-secondary ms-2">{{ __('messages.results.status_pending') }}</span>
                                @endif
                            </p>
                            
                            {{-- Liste des emails destinataires --}}
                            <div class="mt-2">
                                <small class="text-muted d-block mb-1">
                                    <i class="fas fa-envelope"></i> {{ __('messages.results.recipient_emails') }} ({{ $test->emailAccounts->count() }}) :
                                </small>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="email-list-container" style="max-width: 600px;">
                                        <code class="small" id="email-list">{{ $test->emailAccounts->pluck('email')->implode(', ') }}</code>
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="copyEmailList(event)" title="{{ __('messages.results.copy_email_list') }}">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEmailList()" title="{{ __('messages.results.toggle_email_list') }}">
                                        <i class="fas fa-eye" id="toggle-icon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            @if($test->status === 'pending' || $test->status === 'in_progress')
                                @php
                                    $timeoutMinutes = config('mailsoar.email_check_timeout_minutes', 30);
                                    $createdAt = $test->created_at;
                                    $expiresAt = $createdAt->copy()->addMinutes($timeoutMinutes);
                                    $now = now();
                                    $isExpired = $now->greaterThan($expiresAt);
                                @endphp
                                
                                @if(!$isExpired)
                                    <div class="text-center p-3 bg-light rounded">
                                        <small class="text-muted d-block mb-1">
                                            <i class="fas fa-hourglass-half"></i> {{ __('messages.results.time_remaining') }}
                                        </small>
                                        <div class="h3 mb-0 font-monospace" id="countdown-timer">
                                            <span id="countdown-display">--:--</span>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                    
                    <!-- Barre de progression -->
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ __('messages.results.test_progress') }}</span>
                            <span>{{ $receivedEmails }} / {{ $totalEmails }} {{ __('messages.results.emails_analyzed') }}</span>
                        </div>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: {{ $totalEmails > 0 ? ($receivedEmails / $totalEmails) * 100 : 0 }}%">
                                {{ $totalEmails > 0 ? round(($receivedEmails / $totalEmails) * 100) : 0 }}%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Loader si aucun email reçu --}}
            @if($receivedEmails == 0 && in_array($test->status, ['pending', 'in_progress']))
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-body text-center py-5">
                                <div class="spinner-border text-primary mb-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <h5 class="mb-3">{{ __('messages.results.waiting_for_emails') }}</h5>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-info-circle"></i> 
                                    {{ __('messages.results.emails_may_take_time_desc') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Cartes de statistiques principales -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card text-center h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-inbox text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="card-title">{{ __('messages.results.inbox') }}</h5>
                            <h2 class="text-success mb-1">{{ $inboxTotalCount }}</h2>
                            <p class="text-muted mb-0">{{ $inboxRate }}% {{ __('messages.results.of_total') }}</p>
                            @php
                                $includedPlacements = [];
                                foreach ($additionalInboxPlacements as $placement) {
                                    $count = $placementCounts->get($placement, 0);
                                    if ($count > 0) {
                                        $includedPlacements[] = $count . ' ' . ucfirst($placement);
                                    }
                                }
                            @endphp
                            @if(count($includedPlacements) > 0)
                            <small class="text-info" title="Ces dossiers sont comptés comme inbox">
                                ({{ __('messages.results.includes') }}: {{ implode(', ', $includedPlacements) }})
                            </small>
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card text-center h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="card-title">{{ __('messages.results.spam') }}</h5>
                            <h2 class="text-danger mb-1">{{ $spamCount }}</h2>
                            <p class="text-muted mb-0">{{ $spamRate }}% {{ __('messages.results.of_total') }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card text-center h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-times-circle text-secondary" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="card-title">
                                @if($test->status === 'timeout')
                                    Timeout
                                @else
                                    {{ __('messages.results.not_received') }}
                                @endif
                            </h5>
                            <h2 class="text-secondary mb-1">{{ $notReceivedCount }}</h2>
                            <p class="text-muted mb-0">{{ $notReceivedRate }}% {{ __('messages.results.of_total') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Call to Action Calendly -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-primary shadow-sm">
                        <div class="card-body text-center py-4">
                            <div class="row align-items-center">
                                <div class="col-lg-8 text-lg-start text-center mb-3 mb-lg-0">
                                    <h4 class="mb-2 text-primary">
                                        <i class="fas fa-rocket me-2"></i>
                                        {{ __('messages.home.cta_improve_deliverability') }}
                                    </h4>
                                    <p class="mb-0 text-muted">
                                        {{ __('messages.home.cta_experts_help') }}
                                    </p>
                                </div>
                                <div class="col-lg-4 text-lg-end text-center">
                                    <button type="button" class="btn btn-primary btn-lg px-4" onclick="openCalendly()">
                                        <i class="fas fa-calendar-check me-2"></i>
                                        {{ __('messages.home.cta_book_free_call') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphiques -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> {{ __('messages.results.placement_distribution') }}</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="placementChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> {{ __('messages.results.provider_performance') }}</h5>
                                @if(count($providerStats) > 8)
                                <small class="text-muted">{{ __('messages.results.top_providers', ['count' => 8, 'total' => count($providerStats)]) }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="providerChart" height="300"></canvas>
                            @if(count($providerStats) > 8)
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    {{ __('messages.results.other_providers_grouped', ['count' => count($providerStats) - 7]) }}
                                </small>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-filter"></i> {{ __('messages.results.antispam_filters') }}</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="antispamChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analyse d'authentification -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-shield-alt"></i> {{ __('messages.results.authentication_analysis') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row justify-content-center">
                        <div class="col-md-3">
                            <div class="authentication-stat">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon me-3">
                                        @if($receivedEmails > 0 && $spfPass / $receivedEmails >= 0.8)
                                            <i class="fas fa-check-circle text-success" style="font-size: 2.5rem;"></i>
                                        @elseif($receivedEmails > 0 && $spfPass / $receivedEmails >= 0.5)
                                            <i class="fas fa-exclamation-circle text-warning" style="font-size: 2.5rem;"></i>
                                        @else
                                            <i class="fas fa-times-circle text-danger" style="font-size: 2.5rem;"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <h6 class="mb-1">SPF</h6>
                                        <h5 class="mb-0">{{ $spfPass }} / {{ $receivedEmails }}</h5>
                                        <p class="text-muted mb-0 small">
                                            {{ $receivedEmails > 0 ? round(($spfPass / $receivedEmails) * 100) : 0 }}%
                                        </p>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: {{ $receivedEmails > 0 ? ($spfPass / $receivedEmails) * 100 : 0 }}%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="authentication-stat">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon me-3">
                                        @if($receivedEmails > 0 && $dkimPass / $receivedEmails >= 0.8)
                                            <i class="fas fa-check-circle text-success" style="font-size: 2.5rem;"></i>
                                        @elseif($receivedEmails > 0 && $dkimPass / $receivedEmails >= 0.5)
                                            <i class="fas fa-exclamation-circle text-warning" style="font-size: 2.5rem;"></i>
                                        @else
                                            <i class="fas fa-times-circle text-danger" style="font-size: 2.5rem;"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <h6 class="mb-1">DKIM</h6>
                                        <h5 class="mb-0">{{ $dkimPass }} / {{ $receivedEmails }}</h5>
                                        <p class="text-muted mb-0 small">
                                            {{ $receivedEmails > 0 ? round(($dkimPass / $receivedEmails) * 100) : 0 }}%
                                        </p>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: {{ $receivedEmails > 0 ? ($dkimPass / $receivedEmails) * 100 : 0 }}%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="authentication-stat">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon me-3">
                                        @if($receivedEmails > 0 && $dmarcPass / $receivedEmails >= 0.8)
                                            <i class="fas fa-check-circle text-success" style="font-size: 2.5rem;"></i>
                                        @elseif($receivedEmails > 0 && $dmarcPass / $receivedEmails >= 0.5)
                                            <i class="fas fa-exclamation-circle text-warning" style="font-size: 2.5rem;"></i>
                                        @else
                                            <i class="fas fa-times-circle text-danger" style="font-size: 2.5rem;"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <h6 class="mb-1">DMARC</h6>
                                        <h5 class="mb-0">{{ $dmarcPass }} / {{ $receivedEmails }}</h5>
                                        <p class="text-muted mb-0 small">
                                            {{ $receivedEmails > 0 ? round(($dmarcPass / $receivedEmails) * 100) : 0 }}%
                                        </p>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: {{ $receivedEmails > 0 ? ($dmarcPass / $receivedEmails) * 100 : 0 }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau détaillé -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-list"></i> {{ __('messages.results.details_by_email') }}</h5>
                </div>
                <div class="card-body">
                    {{-- Filtres --}}
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label small">{{ __('messages.results.filter_by_provider') }}</label>
                            <select class="form-select form-select-sm" id="filterProvider">
                                <option value="">{{ __('messages.results.all_providers') }}</option>
                                @php
                                    // Récupérer uniquement les providers présents dans ce test
                                    $testProviders = [];
                                    foreach ($test->emailAccounts as $account) {
                                        $provider = $account->getRealProvider();
                                        if (!in_array($provider, $testProviders)) {
                                            $testProviders[] = $provider;
                                        }
                                    }
                                    sort($testProviders);
                                @endphp
                                @foreach($testProviders as $provider)
                                    <option value="{{ $provider }}">{{ $provider }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">{{ __('messages.results.filter_by_status') }}</label>
                            <select class="form-select form-select-sm" id="filterStatus">
                                <option value="">{{ __('messages.results.all_statuses') }}</option>
                                <option value="received">{{ __('messages.results.received') }}</option>
                                <option value="not_received">{{ __('messages.results.not_received') }} / Timeout</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">{{ __('messages.results.filter_by_placement') }}</label>
                            <select class="form-select form-select-sm" id="filterPlacement">
                                <option value="">{{ __('messages.results.all_placements') }}</option>
                                <option value="inbox">Inbox</option>
                                <option value="spam">Spam</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-sm btn-secondary" onclick="resetFilters()">
                                <i class="fas fa-redo"></i> {{ __('messages.results.reset_filters') }}
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="resultsTable">
                            <thead>
                                <tr>
                                    <th>{{ __('messages.results.email') }}</th>
                                    <th>{{ __('messages.results.provider') }}</th>
                                    <th>{{ __('messages.results.type') }}</th>
                                    <th>{{ __('messages.results.status') }}</th>
                                    <th>{{ __('messages.results.placement') }}</th>
                                    <th>{{ __('messages.results.from') }}</th>
                                    <th>SPF</th>
                                    <th>DKIM</th>
                                    <th>DMARC</th>
                                    <th>{{ __('messages.results.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($test->emailAccounts as $account)
                                @php
                                    $receivedEmail = $test->receivedEmails->where('email_account_id', $account->id)->first();
                                    $realProvider = $account->getRealProvider();
                                    $status = $receivedEmail ? 'received' : ($test->status === 'timeout' ? 'timeout' : 'not_received');
                                    $placement = $receivedEmail ? $receivedEmail->placement : '';
                                @endphp
                                <tr data-provider="{{ $realProvider }}" data-status="{{ $status }}" data-placement="{{ $placement }}">
                                    <td class="font-monospace">{{ $account->email }}</td>
                                    <td>
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
                                            // Déterminer si B2B ou B2C basé sur le domaine email
                                            $domain = substr(strrchr($account->email, '@'), 1);
                                            $b2cDomains = ['gmail.com', 'yahoo.com', 'yahoo.fr', 'hotmail.com', 'hotmail.fr', 
                                                          'outlook.com', 'outlook.fr', 'live.com', 'live.fr', 'msn.com',
                                                          'orange.fr', 'wanadoo.fr', 'free.fr', 'sfr.fr', 'laposte.net',
                                                          'icloud.com', 'me.com', 'mac.com', 'aol.com', 'gmx.com', 'yandex.com'];
                                            $isB2C = in_array(strtolower($domain), $b2cDomains);
                                        @endphp
                                        <span class="badge {{ $isB2C ? 'bg-info' : 'bg-secondary' }}">
                                            {{ $isB2C ? 'B2C' : 'B2B' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($receivedEmail)
                                            <span class="badge bg-success">{{ __('messages.results.received') }}</span>
                                        @else
                                            @if($test->status === 'timeout')
                                                <span class="badge bg-warning">Timeout</span>
                                            @else
                                                <span class="badge bg-secondary">{{ __('messages.results.not_received') }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        @if($receivedEmail)
                                            @if($receivedEmail->placement === 'inbox')
                                                <span class="badge bg-success">Inbox</span>
                                            @elseif($receivedEmail->placement === 'spam')
                                                <span class="badge bg-danger">Spam</span>
                                            @elseif(in_array($receivedEmail->placement, ['promotions', 'updates', 'social', 'forums']))
                                                {{-- Afficher badge Inbox + badge alternatif --}}
                                                <span class="badge bg-success me-1">Inbox</span>
                                                @if($receivedEmail->placement === 'promotions')
                                                    <span class="badge bg-warning">Promotions</span>
                                                @elseif($receivedEmail->placement === 'updates')
                                                    <span class="badge bg-info">Updates</span>
                                                @elseif($receivedEmail->placement === 'social')
                                                    <span class="badge bg-primary">Social</span>
                                                @elseif($receivedEmail->placement === 'forums')
                                                    <span class="badge bg-secondary">Forums</span>
                                                @endif
                                            @else
                                                {{-- Autres placements alternatifs --}}
                                                <span class="badge bg-success me-1">Inbox</span>
                                                <span class="badge bg-info">{{ ucfirst($receivedEmail->placement) }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($receivedEmail && $receivedEmail->from_email)
                                            <span class="font-monospace small">{{ $receivedEmail->from_email }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($receivedEmail)
                                            @if($receivedEmail->spf_result === 'pass')
                                                <i class="fas fa-check-circle text-success" title="Pass"></i>
                                            @elseif($receivedEmail->spf_result === 'softfail')
                                                <i class="fas fa-exclamation-circle text-warning" title="Soft fail"></i>
                                            @elseif($receivedEmail->spf_result === 'fail')
                                                <i class="fas fa-times-circle text-danger" title="Fail"></i>
                                            @else
                                                <i class="fas fa-question-circle text-secondary" title="{{ $receivedEmail->spf_result }}"></i>
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($receivedEmail)
                                            @if($receivedEmail->dkim_result === 'pass')
                                                <i class="fas fa-check-circle text-success" title="Pass"></i>
                                            @elseif($receivedEmail->dkim_result === 'fail')
                                                <i class="fas fa-times-circle text-danger" title="Fail"></i>
                                            @else
                                                <i class="fas fa-question-circle text-secondary" title="{{ $receivedEmail->dkim_result }}"></i>
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($receivedEmail)
                                            @if($receivedEmail->dmarc_result === 'pass')
                                                <i class="fas fa-check-circle text-success" title="Pass"></i>
                                            @elseif($receivedEmail->dmarc_result === 'fail')
                                                <i class="fas fa-times-circle text-danger" title="Fail"></i>
                                            @else
                                                <i class="fas fa-question-circle text-secondary" title="{{ $receivedEmail->dmarc_result }}"></i>
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($receivedEmail)
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="showEmailDetails({{ $receivedEmail->id }})"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#emailDetailModal">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Analyse des filtres anti-spam -->
            <div class="card mt-4 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-shield-virus"></i> {{ __('messages.results.antispam_filter_analysis') }}</h5>
                </div>
                <div class="card-body">
                    @php
                        // Collecter tous les systèmes anti-spam détectés
                        $antispamSystems = [];
                        $accountAntispamMap = [];
                        
                        foreach ($test->emailAccounts as $account) {
                            $accountAntispamMap[$account->id] = [];
                            // Récupérer les systèmes anti-spam de ce compte
                            foreach ($account->antispamSystems as $antispam) {
                                $accountAntispamMap[$account->id][] = $antispam->name;
                                $displayName = $antispam->display_name;
                                if (!isset($antispamSystems[$displayName])) {
                                    $antispamSystems[$displayName] = [
                                        'system_name' => $antispam->name,
                                        'accounts' => [],
                                        'emails_received' => 0,
                                        'inbox' => 0,
                                        'spam' => 0,
                                        'promotions' => 0,
                                        'scores' => []
                                    ];
                                }
                                $antispamSystems[$displayName]['accounts'][] = $account->email;
                            }
                        }
                        
                        // Analyser les résultats par système anti-spam
                        foreach ($test->receivedEmails as $email) {
                            $accountId = $email->email_account_id;
                            if (isset($accountAntispamMap[$accountId])) {
                                foreach ($accountAntispamMap[$accountId] as $systemName) {
                                    // Trouver le display name correspondant
                                    $displayNameKey = null;
                                    foreach ($antispamSystems as $displayName => $data) {
                                        if ($data['system_name'] === $systemName) {
                                            $displayNameKey = $displayName;
                                            break;
                                        }
                                    }
                                    
                                    if ($displayNameKey) {
                                        $antispamSystems[$displayNameKey]['emails_received']++;
                                        
                                        // Compter par placement
                                        if (in_array($email->placement, $inboxPlacements)) {
                                            $antispamSystems[$displayNameKey]['inbox']++;
                                        } elseif ($email->placement === 'spam') {
                                            $antispamSystems[$displayNameKey]['spam']++;
                                        } elseif ($email->placement === 'promotions') {
                                            $antispamSystems[$displayNameKey]['promotions']++;
                                        }
                                        
                                        // Collecter les scores si disponibles
                                        if ($email->spam_scores && is_array($email->spam_scores)) {
                                            foreach ($email->spam_scores as $filter => $score) {
                                                if (stripos($filter, strtolower($systemName)) !== false || 
                                                    stripos($systemName, $filter) !== false) {
                                                    $antispamSystems[$displayNameKey]['scores'][] = $score;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    @endphp
                    
                    @if(count($antispamSystems) > 0)
                        <div class="row mb-3">
                            <div class="col-12">
                                <p class="text-muted mb-3">
                                    <i class="fas fa-info-circle"></i> 
                                    {{ __('messages.results.antispam_systems_detected', ['count' => count($antispamSystems), 'total' => $test->emailAccounts->count()]) }}
                                </p>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('messages.results.antispam_system') }}</th>
                                        <th>{{ __('messages.results.accounts') }}</th>
                                        <th>{{ __('messages.results.emails_received_count') }}</th>
                                        <th>{{ __('messages.results.placement') }}</th>
                                        <th>{{ __('messages.results.performance') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($antispamSystems as $systemName => $data)
                                    @php
                                        $inboxRate = $data['emails_received'] > 0 ? 
                                            round(($data['inbox'] / $data['emails_received']) * 100) : 0;
                                        $spamRate = $data['emails_received'] > 0 ? 
                                            round(($data['spam'] / $data['emails_received']) * 100) : 0;
                                        $avgScore = count($data['scores']) > 0 ? 
                                            round(array_sum($data['scores']) / count($data['scores']), 2) : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $systemName }}</strong>
                                            @if($avgScore !== null)
                                                <br>
                                                <small class="text-muted">{{ __('messages.results.average_score_label') }}: {{ $avgScore }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">{{ count(array_unique($data['accounts'])) }}</span>
                                        </td>
                                        <td>
                                            {{ $data['emails_received'] }} / {{ count(array_unique($data['accounts'])) }}
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                @if($data['inbox'] > 0)
                                                    <span class="badge bg-success">Inbox: {{ $data['inbox'] }}</span>
                                                @endif
                                                @if($data['spam'] > 0)
                                                    <span class="badge bg-danger">Spam: {{ $data['spam'] }}</span>
                                                @endif
                                                @if($data['promotions'] > 0)
                                                    <span class="badge bg-warning">Promo: {{ $data['promotions'] }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 25px; min-width: 150px;">
                                                @if($inboxRate > 0)
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: {{ $inboxRate }}%"
                                                         data-bs-toggle="tooltip" 
                                                         title="Inbox: {{ $inboxRate }}%">
                                                        {{ $inboxRate > 10 ? $inboxRate . '%' : '' }}
                                                    </div>
                                                @endif
                                                @if($spamRate > 0)
                                                    <div class="progress-bar bg-danger" role="progressbar" 
                                                         style="width: {{ $spamRate }}%"
                                                         data-bs-toggle="tooltip" 
                                                         title="Spam: {{ $spamRate }}%">
                                                        {{ $spamRate > 10 ? $spamRate . '%' : '' }}
                                                    </div>
                                                @endif
                                                @if($data['emails_received'] == 0)
                                                    <div class="progress-bar bg-secondary" role="progressbar" 
                                                         style="width: 100%">
                                                        {{ __('messages.results.no_email') }}
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle"></i> 
                            {{ __('messages.results.no_antispam_configured') }} 
                            <a href="{{ route('admin.email-accounts.index') }}" class="alert-link">{{ __('messages.results.configure_antispam') }}</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal détail email -->
<div class="modal fade" id="emailDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('messages.results.email_full_details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="emailDetailContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
/* Fix global pour éviter le scroll horizontal */
html, body {
    max-width: 100%;
    overflow-x: hidden !important;
}

/* Fix spécifique quand Calendly est actif */
body:has(.calendly-overlay) {
    overflow: hidden !important;
    position: fixed !important;
    width: 100% !important;
}

.text-purple {
    color: #6f42c1;
}

.authentication-stat {
    padding: 1rem;
    border-radius: 8px;
    background-color: #f8f9fa;
}

.font-monospace {
    font-family: 'Courier New', Courier, monospace;
}

@media print {
    .btn-group {
        display: none !important;
    }
}

.table th {
    white-space: nowrap;
}

.table td.font-monospace {
    word-break: break-all;
    max-width: 120px;
}

.email-list-container {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
}

.email-list-container.expanded {
    white-space: normal;
    word-break: break-word;
    overflow: visible;
    max-width: none !important;
}

/* Styles pour le CTA Calendly */
.card.border-primary {
    border-width: 2px !important;
    background: linear-gradient(135deg, #f5f7ff 0%, #ffffff 100%);
}

.card.border-primary:hover {
    box-shadow: 0 8px 25px rgba(0, 105, 217, 0.15) !important;
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

.btn-primary.btn-lg {
    font-weight: 600;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(0, 105, 217, 0.25);
    transition: all 0.3s ease;
}

.btn-primary.btn-lg:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 105, 217, 0.35);
}

/* Animation pulse pour attirer l'attention */
@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(0, 105, 217, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(0, 105, 217, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(0, 105, 217, 0);
    }
}

.btn-primary.btn-lg:focus {
    animation: pulse 1.5s;
}

/* Fix pour la popup Calendly - Solution complète */
.calendly-overlay {
    z-index: 999999 !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
}

.calendly-popup {
    z-index: 1000000 !important;
    position: fixed !important;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) !important;
    max-width: min(1000px, 95vw) !important;
    max-height: 95vh !important;
    width: 100% !important;
    margin: 0 !important;
    overflow: hidden !important;
}

.calendly-popup-content {
    height: 100% !important;
    width: 100% !important;
    overflow: hidden !important;
    position: relative !important;
}

.calendly-popup-close {
    z-index: 1000001 !important;
    position: absolute !important;
    right: 0 !important;
    top: 0 !important;
}

/* État du body quand Calendly est actif */
body.calendly-popup-active {
    overflow: hidden !important;
    position: fixed !important;
    width: 100vw !important;
    height: 100vh !important;
    top: 0 !important;
    left: 0 !important;
    margin: 0 !important;
    padding-right: 0 !important;
}

/* Conteneur iframe Calendly */
.calendly-inline-widget {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    border: none !important;
    overflow: hidden !important;
}

.calendly-inline-widget iframe {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    border: none !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
}

/* Masquer le badge Calendly */
.calendly-badge-widget,
.calendly-badge-content {
    display: none !important;
}

/* Forcer tous les éléments Calendly à respecter les limites */
[class*="calendly"] {
    max-width: 100% !important;
}

/* Empêcher le scroll sur le conteneur principal pendant que la popup est ouverte */
body.calendly-popup-active .container-fluid,
body.calendly-popup-active .container {
    overflow: hidden !important;
}

/* Fix pour le scroll bar width */
body.calendly-popup-active::-webkit-scrollbar {
    width: 0 !important;
    display: none !important;
}

</style>
@endpush

@push('scripts')
<!-- Calendly widget script -->
<link href="https://assets.calendly.com/assets/external/widget.css" rel="stylesheet">
<script src="https://assets.calendly.com/assets/external/widget.js" type="text/javascript" async></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Translations for JavaScript
const translations = {
    general_info: "{{ __('messages.results.general_info') }}",
    from_label: "{{ __('messages.results.from_label') }}",
    to_label: "{{ __('messages.results.to_label') }}",
    subject_label: "{{ __('messages.results.subject_label') }}",
    date_label: "{{ __('messages.results.date_label') }}",
    size_label: "{{ __('messages.results.size_label') }}",
    analysis_results: "{{ __('messages.results.analysis_results') }}",
    placement_label: "{{ __('messages.results.placement_label') }}",
    spam_score_label: "{{ __('messages.results.spam_score_label') }}",
    email_authentication: "{{ __('messages.results.email_authentication') }}",
    blacklists: "{{ __('messages.results.blacklists') }}",
    full_headers: "{{ __('messages.results.full_headers') }}",
    sending_ip: "{{ __('messages.results.sending_ip') }}",
    reverse_dns_valid: "{{ __('messages.results.reverse_dns_valid') }}",
    no_verification: "{{ __('messages.results.no_verification') }}",
    spf_pass_desc: "{{ __('messages.results.spf_pass_desc') }}",
    spf_fail_desc: "{{ __('messages.results.spf_fail_desc') }}",
    spf_softfail_desc: "{{ __('messages.results.spf_softfail_desc') }}",
    spf_unknown_desc: "{{ __('messages.results.spf_unknown_desc') }}",
    dkim_pass_desc: "{{ __('messages.results.dkim_pass_desc') }}",
    dkim_fail_desc: "{{ __('messages.results.dkim_fail_desc') }}",
    dkim_unknown_desc: "{{ __('messages.results.dkim_unknown_desc') }}",
    dmarc_pass_desc: "{{ __('messages.results.dmarc_pass_desc') }}",
    dmarc_fail_desc: "{{ __('messages.results.dmarc_fail_desc') }}",
    dmarc_unknown_desc: "{{ __('messages.results.dmarc_unknown_desc') }}"
};

// Email data loaded directly without modification

// Données pour les graphiques
const placementData = {
    labels: ['Inbox', 'Spam', @if($test->status === 'timeout') 'Timeout' @else 'Non reçus' @endif],
    datasets: [{
        data: [{{ $inboxTotalCount }}, {{ $spamCount }}, {{ $notReceivedCount }}],
        backgroundColor: ['#28a745', '#dc3545', '#6c757d'],
        borderWidth: 0
    }]
};

const providerData = {
    labels: {!! json_encode(array_keys($providerStats)) !!},
    datasets: [{
        label: 'Inbox',
        data: {!! json_encode(array_column($providerStats, 'inbox')) !!},
        backgroundColor: '#28a745'
    }, {
        label: 'Spam',
        data: {!! json_encode(array_column($providerStats, 'spam')) !!},
        backgroundColor: '#dc3545'
    }]
};

// Nouvelles données pour le graphique anti-spam avec Inbox/Spam/Timeout
const antispamData = {
    labels: {!! json_encode(array_keys($antispamStats)) !!},
    datasets: [{
        label: 'Inbox',
        data: {!! json_encode(array_column($antispamStats, 'inbox')) !!},
        backgroundColor: '#28a745'
    }, {
        label: 'Spam',
        data: {!! json_encode(array_column($antispamStats, 'spam')) !!},
        backgroundColor: '#dc3545'
    }, {
        label: 'Timeout/Not received',
        data: {!! json_encode(array_column($antispamStats, 'timeout')) !!},
        backgroundColor: '#6c757d'
    }]
};

// Graphique de placement
const placementCtx = document.getElementById('placementChart').getContext('2d');
new Chart(placementCtx, {
    type: 'doughnut',
    data: placementData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                padding: 20
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return label + ': ' + value + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Graphique par provider avec limitation pour l'affichage
const maxProvidersInChart = 10;
const providerLabels = {!! json_encode(array_keys($providerStats)) !!};
const providerInbox = {!! json_encode(array_column($providerStats, 'inbox')) !!};
const providerSpam = {!! json_encode(array_column($providerStats, 'spam')) !!};
const providerTimeout = {!! json_encode(array_column($providerStats, 'timeout')) !!};

// Si trop de fournisseurs, regrouper les plus petits
let displayLabels = [...providerLabels];
let displayInbox = [...providerInbox];
let displaySpam = [...providerSpam];
let displayTimeout = [...providerTimeout];

if (providerLabels.length > maxProvidersInChart) {
    // Calculer le total pour chaque fournisseur
    const providerTotals = providerLabels.map((label, index) => ({
        label,
        total: providerInbox[index] + providerSpam[index] + providerTimeout[index],
        index
    }));
    
    // Trier par total décroissant
    providerTotals.sort((a, b) => b.total - a.total);
    
    // Prendre les 7 premiers et regrouper le reste
    const topProviders = providerTotals.slice(0, maxProvidersInChart - 1);
    const otherProviders = providerTotals.slice(maxProvidersInChart - 1);
    
    // Recalculer les données
    displayLabels = topProviders.map(p => p.label).concat(['Autres']);
    displayInbox = topProviders.map(p => providerInbox[p.index]);
    displaySpam = topProviders.map(p => providerSpam[p.index]);
    displayTimeout = topProviders.map(p => providerTimeout[p.index]);
    
    // Ajouter les totaux des autres
    if (otherProviders.length > 0) {
        displayInbox.push(otherProviders.reduce((sum, p) => sum + providerInbox[p.index], 0));
        displaySpam.push(otherProviders.reduce((sum, p) => sum + providerSpam[p.index], 0));
        displayTimeout.push(otherProviders.reduce((sum, p) => sum + providerTimeout[p.index], 0));
    }
}

const optimizedProviderData = {
    labels: displayLabels,
    datasets: [{
        label: 'Inbox',
        data: displayInbox,
        backgroundColor: '#28a745'
    }, {
        label: 'Spam',
        data: displaySpam,
        backgroundColor: '#dc3545'
    }, {
        label: 'Timeout/Not received',
        data: displayTimeout,
        backgroundColor: '#6c757d'
    }]
};

// Graphique par provider
const providerCtx = document.getElementById('providerChart').getContext('2d');
new Chart(providerCtx, {
    type: 'bar',
    data: optimizedProviderData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { 
                stacked: true,
                grid: {
                    display: false
                },
                ticks: {
                    maxRotation: 45,
                    minRotation: 0
                }
            },
            y: { 
                stacked: true,
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        },
        plugins: {
            legend: {
                position: 'bottom',
                padding: 20
            },
            tooltip: {
                callbacks: {
                    afterLabel: function(context) {
                        if (providerLabels.length > maxProvidersInChart && context.label === 'Autres') {
                            return `Regroupe ${otherProviders.length} fournisseurs`;
                        }
                        return '';
                    }
                }
            }
        }
    }
});

// Graphique des filtres anti-spam
const antispamCtx = document.getElementById('antispamChart').getContext('2d');
new Chart(antispamCtx, {
    type: 'bar',
    data: antispamData,
    options: {
        indexAxis: 'y', // Barres horizontales
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: {
                stacked: true,
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            },
            y: {
                stacked: true,
                grid: {
                    display: false
                }
            }
        },
        plugins: {
            legend: {
                position: 'bottom',
                padding: 20
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.x + ' emails';
                    }
                }
            }
        }
    }
});

// Fonction pour afficher les détails d'un email
function showEmailDetails(emailId) {
    // Trouver l'email dans les données
    const emails = @json($test->receivedEmails);
    const email = emails.find(e => e.id === emailId);
    
    if (!email) return;
    
    let blacklistsHtml = '';
    if (email.blacklist_results) {
        for (const [list, status] of Object.entries(email.blacklist_results)) {
            blacklistsHtml += `
                <span class="badge ${status ? 'bg-danger' : 'bg-success'} me-2">
                    ${list}: ${status ? 'Listed' : 'Clean'}
                </span>
            `;
        }
    }
    
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6>{{ __('messages.results.general_info') }}</h6>
                <table class="table table-sm">
                    <tr><th>{{ __('messages.results.from_label') }}</th><td>${email.from_email || 'N/A'}</td></tr>
                    <tr><th>{{ __('messages.results.to_label') }}</th><td>${email.email_account?.email || 'N/A'}</td></tr>
                    <tr><th>{{ __('messages.results.subject_label') }}</th><td>${email.subject}</td></tr>
                    <tr><th>{{ __('messages.results.date_label') }}</th><td>${new Date(email.email_date).toLocaleString('fr-FR')}</td></tr>
                    <tr><th>{{ __('messages.results.size_label') }}</th><td>${(email.size_bytes / 1024).toFixed(2)} KB</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>{{ __('messages.results.analysis_results') }}</h6>
                <table class="table table-sm">
                    <tr><th>{{ __('messages.results.placement_label') }}</th><td><span class="badge bg-${email.placement === 'inbox' ? 'success' : (email.placement === 'spam' ? 'danger' : 'warning')}">${email.placement}</span></td></tr>
                    <tr><th>{{ __('messages.results.spam_score_label') }}</th><td>${email.spam_scores?.spamassassin || 'N/A'}</td></tr>
                </table>
                
                <h6 class="mt-3">{{ __('messages.results.email_authentication') }}</h6>
                <div class="authentication-details">
                    <div class="auth-item mb-2">
                        <strong>SPF :</strong> 
                        <span class="badge bg-${email.spf_result === 'pass' ? 'success' : (email.spf_result === 'fail' ? 'danger' : 'warning')}">${email.spf_result}</span>
                        <small class="text-muted d-block">
                            ${email.spf_result === 'pass' ? '✓ ' + translations.spf_pass_desc : 
                              email.spf_result === 'fail' ? '✗ ' + translations.spf_fail_desc : 
                              email.spf_result === 'softfail' ? '⚠ ' + translations.spf_softfail_desc : 
                              '? ' + translations.spf_unknown_desc}
                        </small>
                    </div>
                    
                    <div class="auth-item mb-2">
                        <strong>DKIM :</strong> 
                        <span class="badge bg-${email.dkim_result === 'pass' ? 'success' : (email.dkim_result === 'fail' ? 'danger' : 'warning')}">${email.dkim_result}</span>
                        <small class="text-muted d-block">
                            ${email.dkim_result === 'pass' ? '✓ ' + translations.dkim_pass_desc : 
                              email.dkim_result === 'fail' ? '✗ ' + translations.dkim_fail_desc : 
                              '? ' + translations.dkim_unknown_desc}
                        </small>
                    </div>
                    
                    <div class="auth-item mb-2">
                        <strong>DMARC :</strong> 
                        <span class="badge bg-${email.dmarc_result === 'pass' ? 'success' : (email.dmarc_result === 'fail' ? 'danger' : 'warning')}">${email.dmarc_result}</span>
                        <small class="text-muted d-block">
                            ${email.dmarc_result === 'pass' ? '✓ ' + translations.dmarc_pass_desc : 
                              email.dmarc_result === 'fail' ? '✗ ' + translations.dmarc_fail_desc : 
                              '? ' + translations.dmarc_unknown_desc}
                        </small>
                    </div>
                    
                </div>
                
                ${email.sending_ip ? `
                <div class="mt-2">
                    <strong>{{ __('messages.results.sending_ip') }}</strong> ${email.sending_ip}
                    ${email.reverse_dns_valid ? '<i class="fas fa-check-circle text-success ms-1" title="' + translations.reverse_dns_valid + '"></i>' : ''}
                </div>
                ` : ''}
            </div>
        </div>
        
        <div class="mt-3">
            <h6>{{ __('messages.results.blacklists') }}</h6>
            <div>${blacklistsHtml || '<span class="text-muted">' + translations.no_verification + '</span>'}</div>
        </div>
        
        <div class="mt-3">
            <h6>{{ __('messages.results.full_headers') }}</h6>
            <pre class="bg-light p-3" style="max-height: 400px; overflow-y: auto;">${email.raw_headers}</pre>
        </div>
    `;
    
    document.getElementById('emailDetailContent').innerHTML = content;
}


// Fonction pour copier la liste des emails
function copyEmailList(event) {
    const emailList = document.getElementById('email-list').textContent;
    const button = event.currentTarget;
    
    navigator.clipboard.writeText(emailList).then(function() {
        // Afficher un feedback visuel
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check text-success"></i>';
        button.classList.add('btn-success');
        button.classList.remove('btn-outline-secondary');
        
        setTimeout(function() {
            button.innerHTML = originalContent;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
        
        // Afficher un toast
        showToast('success', '{{ __('messages.results.emails_copied_success') }}');
    }).catch(function(err) {
        showToast('danger', '{{ __('messages.results.copy_error') }}');
    });
}

// Fonction pour afficher/masquer la liste complète des emails
function toggleEmailList() {
    const container = document.querySelector('.email-list-container');
    const icon = document.getElementById('toggle-icon');
    
    if (container.classList.contains('expanded')) {
        container.classList.remove('expanded');
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    } else {
        container.classList.add('expanded');
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    }
}

// Fonction pour afficher un toast (simple implementation)
function showToast(type, message) {
    // Créer un conteneur pour les toasts s'il n'existe pas
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
    
    // Créer le toast
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
    
    // Afficher le toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 3000
    });
    toast.show();
    
    // Supprimer le toast après disparition
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Fonction pour calculer la largeur de la scrollbar
function getScrollbarWidth() {
    const outer = document.createElement('div');
    outer.style.visibility = 'hidden';
    outer.style.overflow = 'scroll';
    outer.style.msOverflowStyle = 'scrollbar';
    document.body.appendChild(outer);
    
    const inner = document.createElement('div');
    outer.appendChild(inner);
    
    const scrollbarWidth = outer.offsetWidth - inner.offsetWidth;
    outer.parentNode.removeChild(outer);
    
    return scrollbarWidth;
}

// Fonction pour ouvrir Calendly
function openCalendly() {
    const calendlyUrl = 'https://calendly.com/pierre-mailsoar/talk-expert-test-deliverability';
    
    // Calculer la largeur de la scrollbar
    const scrollbarWidth = getScrollbarWidth();
    
    // Sauvegarder la position de scroll actuelle
    const scrollY = window.scrollY;
    const scrollX = window.scrollX;
    
    // Sauvegarder la largeur originale du body
    const originalBodyWidth = document.body.style.width;
    const originalBodyPaddingRight = document.body.style.paddingRight;
    
    // Compenser la largeur de la scrollbar pour éviter le décalage
    const currentPadding = parseInt(window.getComputedStyle(document.body).paddingRight || '0');
    document.body.style.paddingRight = `${currentPadding + scrollbarWidth}px`;
    
    // Ajouter une classe au body pour gérer le scroll et mémoriser la position
    document.body.classList.add('calendly-popup-active');
    document.body.style.top = `-${scrollY}px`;
    document.body.style.width = '100%';
    
    // Forcer le viewport à rester en place
    window.scrollTo(0, 0);
    
    // Ouvrir dans un popup Calendly
    Calendly.initPopupWidget({
        url: calendlyUrl,
        text: 'Réserver un appel',
        color: '#0069d9',
        textColor: '#ffffff',
        branding: false,
        prefill: {
            @if($test->visitor_email)
            email: '{{ $test->visitor_email }}', // Email du créateur du test
            @endif
            customAnswers: {
                // Ne pas remplir "What's your company?" (a1)
                // Ne pas remplir "Phone number" (a2)
                // Remplir "Can you share with us an overview of the issue" avec le lien du test
                a3: 'Test result: {{ url("/test/{$test->unique_id}/results") }}'
            }
        },
        parentElement: document.body,
        utm: {}
    });
    
    // Écouter la fermeture de la popup pour retirer la classe et restaurer le scroll
    setTimeout(() => {
        const checkPopupClosed = setInterval(() => {
            const popup = document.querySelector('.calendly-overlay');
            if (!popup) {
                // Restaurer le comportement normal
                document.body.classList.remove('calendly-popup-active');
                const scrollTop = Math.abs(parseInt(document.body.style.top || '0'));
                document.body.style.top = '';
                document.body.style.width = originalBodyWidth;
                document.body.style.paddingRight = originalBodyPaddingRight;
                window.scrollTo(scrollX, scrollTop);
                clearInterval(checkPopupClosed);
            }
        }, 500);
    }, 1000);
}

// Initialize Bootstrap tooltips and popovers
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

// Countdown timer
@if($test->status === 'pending' || $test->status === 'in_progress')
    @php
        $timeoutMinutes = config('mailsoar.email_check_timeout_minutes', 30);
        $expiresAt = $test->created_at->copy()->addMinutes($timeoutMinutes);
    @endphp
    
    @if(now()->lessThan($expiresAt))
        const expiresAt = new Date('{{ $expiresAt->toIso8601String() }}');
        
        function updateCountdown() {
            const now = new Date();
            const diff = expiresAt - now;
            
            if (diff <= 0) {
                document.getElementById('countdown-display').textContent = '00:00';
                document.getElementById('countdown-timer').classList.add('text-danger');
                // Reload once to show timeout status
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
                return;
            }
            
            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            
            const display = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            const countdownDisplay = document.getElementById('countdown-display');
            if (countdownDisplay) {
                countdownDisplay.textContent = display;
                
                // Change color based on time remaining
                const countdownTimer = document.getElementById('countdown-timer');
                if (countdownTimer) {
                    if (minutes < 5) {
                        countdownTimer.classList.add('text-danger');
                        countdownTimer.classList.remove('text-warning');
                    } else if (minutes < 10) {
                        countdownTimer.classList.add('text-warning');
                        countdownTimer.classList.remove('text-danger');
                    }
                }
            }
        }
        
        // Update immediately and then every second
        updateCountdown();
        setInterval(updateCountdown, 1000);
        
        // Auto-refresh every 30 seconds if test is still in progress
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    @endif
@endif

// Système de filtrage
function applyFilters() {
    const providerFilter = document.getElementById('filterProvider').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const placementFilter = document.getElementById('filterPlacement').value;
    
    const rows = document.querySelectorAll('#resultsTable tbody tr');
    
    rows.forEach(row => {
        const provider = row.getAttribute('data-provider').toLowerCase();
        const status = row.getAttribute('data-status');
        const placement = row.getAttribute('data-placement');
        
        let show = true;
        
        if (providerFilter && provider !== providerFilter) {
            show = false;
        }
        
        if (statusFilter) {
            if (statusFilter === 'not_received' && (status === 'not_received' || status === 'timeout')) {
                // Show both not_received and timeout when filtering by not_received
            } else if (status !== statusFilter) {
                show = false;
            }
        }
        
        if (placementFilter) {
            if (placementFilter === 'inbox') {
                // Considérer toutes les inbox alternatives comme inbox
                const inboxPlacements = ['inbox', 'promotions', 'updates', 'social', 'forums'];
                if (!inboxPlacements.includes(placement)) {
                    show = false;
                }
            } else if (placement !== placementFilter) {
                show = false;
            }
        }
        
        row.style.display = show ? '' : 'none';
    });
}

function resetFilters() {
    document.getElementById('filterProvider').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterPlacement').value = '';
    applyFilters();
}

// Ajouter les événements aux filtres
document.getElementById('filterProvider').addEventListener('change', applyFilters);
document.getElementById('filterStatus').addEventListener('change', applyFilters);
document.getElementById('filterPlacement').addEventListener('change', applyFilters);
</script>

@endpush


@push('styles')
@endpush

@endsection

@push('scripts')
<script>
// Ajouter le bouton Talk to Expert après le chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.floating-buttons-container');
    if (container) {
        // Créer le bouton Talk to Expert
        const expertBtn = document.createElement('button');
        expertBtn.type = 'button';
        expertBtn.className = 'btn btn-warning floating-btn';
        expertBtn.onclick = openCalendly;
        expertBtn.innerHTML = '<i class="fas fa-headset me-2"></i>{{ __('messages.home.cta_talk_expert') }}';
        
        // Insérer le bouton avant le bouton My Tests
        const myTestsBtn = container.querySelector('.floating-btn');
        container.insertBefore(expertBtn, myTestsBtn);
    }
});
</script>
@endpush
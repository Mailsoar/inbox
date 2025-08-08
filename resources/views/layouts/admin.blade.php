<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'Administration') - Inbox by MailSoar</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Admin Styles -->
    <style>
        :root {
            --admin-primary: #2c5aa0;
            --admin-secondary: #6c757d;
            --admin-success: #198754;
            --admin-warning: #fd7e14;
            --admin-danger: #dc3545;
            --admin-sidebar: #1e293b;
            --admin-sidebar-hover: #334155;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .admin-sidebar {
            background: linear-gradient(135deg, var(--admin-sidebar) 0%, #0f172a 100%);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .admin-sidebar .nav-link {
            color: #cbd5e1;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 8px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .admin-sidebar .nav-link:hover {
            background-color: var(--admin-sidebar-hover);
            color: white;
            transform: translateX(4px);
            border-left-color: var(--admin-primary);
        }

        .admin-sidebar .nav-link.active {
            background-color: var(--admin-primary);
            color: white;
            border-left-color: #fbbf24;
        }

        .admin-sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .admin-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid #e2e8f0;
        }

        .admin-content {
            padding: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-card.primary .stat-icon { background: var(--admin-primary); }
        .stat-card.success .stat-icon { background: var(--admin-success); }
        .stat-card.warning .stat-icon { background: var(--admin-warning); }
        .stat-card.danger .stat-icon { background: var(--admin-danger); }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 8px 0;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .table-admin {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .table-admin thead {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-admin thead th {
            font-weight: 600;
            color: #374151;
            border: none;
            padding: 16px;
        }

        .table-admin tbody td {
            padding: 16px;
            border-color: #f1f5f9;
            vertical-align: middle;
        }

        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .btn-admin {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-admin:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .page-title {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #64748b;
            margin-bottom: 24px;
        }

        .dropdown-user {
            border: none;
            background: none;
            color: #64748b;
        }

        .dropdown-user:hover {
            color: var(--admin-primary);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--admin-danger);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-auto admin-sidebar">
                <div class="p-3">
                    <!-- Logo -->
                    <div class="text-center mb-4">
                        <h4 class="text-white fw-bold mb-0">
                            <i class="fas fa-envelope-open-text text-warning me-2"></i>
                            MailSoar
                        </h4>
                        <small class="text-muted">Administration</small>
                    </div>
                    
                    <!-- Navigation -->
                    <nav class="nav flex-column">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" 
                           href="{{ route('admin.dashboard') }}">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                        
                        <a class="nav-link {{ request()->routeIs('admin.tests.*') ? 'active' : '' }}" 
                           href="{{ route('admin.tests.index') }}">
                            <i class="fas fa-flask"></i>
                            Tests
                        </a>
                        
                        <a class="nav-link {{ request()->routeIs('admin.queue.*') ? 'active' : '' }}" 
                           href="{{ route('admin.queue.index') }}">
                            <i class="fas fa-tasks"></i>
                            File d'attente
                            @php
                                $pendingCount = \DB::table('jobs')->where('queue', 'slow-searches')->count();
                            @endphp
                            @if($pendingCount > 0)
                                <span class="badge bg-warning ms-1">{{ $pendingCount }}</span>
                            @endif
                        </a>
                        
                        <a class="nav-link {{ request()->routeIs('admin.email-accounts.*') ? 'active' : '' }}" 
                           href="{{ route('admin.email-accounts.index') }}">
                            <i class="fas fa-at"></i>
                            Comptes Email
                        </a>
                        
                        <a class="nav-link {{ request()->routeIs('admin.antispam-systems.*') ? 'active' : '' }}" 
                           href="{{ route('admin.antispam-systems.index') }}">
                            <i class="fas fa-shield-alt"></i>
                            Systèmes Anti-spam
                        </a>
                        
                        <a class="nav-link {{ request()->routeIs('admin.providers.*') ? 'active' : '' }}" 
                           href="{{ route('admin.providers.index') }}">
                            <i class="fas fa-server"></i>
                            Fournisseurs Email
                        </a>
                        
                        <a class="nav-link {{ request()->routeIs('admin.filter-rules.*') ? 'active' : '' }}" 
                           href="{{ route('admin.filter-rules.index') }}">
                            <i class="fas fa-filter"></i>
                            Règles de filtrage
                        </a>
                        
                        <a class="nav-link {{ request()->routeIs('admin.logs.*') ? 'active' : '' }}" 
                           href="{{ route('admin.logs.index') }}">
                            <i class="fas fa-file-alt"></i>
                            Logs Système
                        </a>
                        
                        @if(auth('admin')->user() && auth('admin')->user()->isSuperAdmin())
                        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" 
                           href="{{ route('admin.users.index') }}">
                            <i class="fas fa-users-cog"></i>
                            Admin Users
                        </a>
                        @endif
                        
                        <hr class="my-3" style="border-color: #334155;">
                        
                        <a class="nav-link" href="{{ route('home') }}" target="_blank">
                            <i class="fas fa-external-link-alt"></i>
                            Site Public
                        </a>
                        
                        <a class="nav-link text-danger" href="{{ route('admin.logout') }}"
                           data-confirm="Êtes-vous sûr de vouloir vous déconnecter ?"
                           data-action="Déconnexion"
                           data-btn-class="btn-danger">
                            <i class="fas fa-sign-out-alt"></i>
                            Déconnexion
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col">
                <!-- Header -->
                <header class="admin-header">
                    <div class="d-flex justify-content-between align-items-center p-3">
                        <div>
                            <h5 class="mb-0 page-title">@yield('page-title', 'Dashboard')</h5>
                            @hasSection('page-subtitle')
                                <p class="mb-0 page-subtitle">@yield('page-subtitle')</p>
                            @endif
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <!-- Timezone info -->
                            <div class="me-4 text-end">
                                <small class="text-muted d-block">
                                    <i class="fas fa-clock"></i> {{ now()->format('H:i') }}
                                </small>
                                <small class="text-muted">Europe/Paris</small>
                            </div>
                            
                            <!-- Quick Stats -->
                            <div class="me-4 text-end">
                                <small class="text-muted d-block">Tests actifs</small>
                                <span class="fw-bold text-primary">{{ $activeTests ?? 0 }}</span>
                            </div>
                            
                            <!-- User Menu -->
                            <div class="dropdown">
                                <button class="btn dropdown-user dropdown-toggle" type="button" 
                                        data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-2"></i>
                                    {{ auth('admin')->user()->name ?? 'Admin' }}
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <span class="dropdown-item-text small text-muted">
                                            {{ auth('admin')->user()->email ?? '' }}
                                        </span>
                                    </li>
                                    @if(auth('admin')->user())
                                    <li>
                                        <span class="dropdown-item-text">
                                            <span class="badge bg-{{ auth('admin')->user()->role === 'super_admin' ? 'danger' : (auth('admin')->user()->role === 'admin' ? 'warning' : 'secondary') }}">
                                                {{ auth('admin')->user()->getRoleDisplayName() }}
                                            </span>
                                        </span>
                                    </li>
                                    @endif
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="{{ route('admin.logout') }}"
                                           data-confirm="Êtes-vous sûr de vouloir vous déconnecter ?"
                                           data-action="Déconnexion"
                                           data-btn-class="btn-danger">
                                            <i class="fas fa-sign-out-alt me-2"></i>
                                            Déconnexion
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Content -->
                <main class="admin-content">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
    </div>

    <!-- Logout Form -->
    <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
        @csrf
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Modal de confirmation globale -->
    @include('admin.partials.confirm-modal')
    
    @stack('scripts')
</body>
</html>
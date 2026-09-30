@extends('layouts.admin')

@section('title', 'Newsletter')

@section('content')
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Newsletter</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-envelope-open-text text-muted me-2"></i>
            Inscrits aux communications
            <span class="badge bg-primary ms-2">{{ $subscribers->total() }}</span>
        </h1>
        <a href="{{ route('admin.newsletter.export') }}" class="btn btn-success">
            <i class="fas fa-download me-1"></i> Télécharger (CSV)
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control"
                           placeholder="Rechercher un email..." value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Rechercher</button>
                    @if(request('search'))
                        <a href="{{ route('admin.newsletter.index') }}" class="btn btn-outline-secondary">Réinitialiser</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Consentement</th>
                            <th>Dernier test</th>
                            <th class="text-center">Tests</th>
                            <th>Langue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subscribers as $subscriber)
                        <tr>
                            <td>{{ $subscriber->email }}</td>
                            <td><small class="text-muted">{{ $subscriber->consent_at ? \Carbon\Carbon::parse($subscriber->consent_at)->format('Y-m-d H:i') : '-' }}</small></td>
                            <td><small class="text-muted">{{ \Carbon\Carbon::parse($subscriber->last_test_at)->format('Y-m-d H:i') }}</small></td>
                            <td class="text-center"><span class="badge bg-primary">{{ $subscriber->test_count }}</span></td>
                            <td>{{ strtoupper($subscriber->language ?? '-') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Aucun inscrit pour le moment</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $subscribers->links() }}
        </div>
    </div>
</div>
@endsection

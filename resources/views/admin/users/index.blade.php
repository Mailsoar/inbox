@extends('layouts.admin')

@section('title', 'Admin Users Management')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Admin Users</h1>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($admins as $admin)
                        <tr>
                            <td>
                                {{ $admin->name }}
                                @if($admin->id === auth('admin')->id())
                                    <span class="badge bg-info ms-2">You</span>
                                @endif
                            </td>
                            <td>{{ $admin->email }}</td>
                            <td>
                                <span class="badge bg-{{ $admin->role === 'super_admin' ? 'danger' : ($admin->role === 'admin' ? 'warning' : 'secondary') }}">
                                    {{ $admin->getRoleDisplayName() }}
                                </span>
                                @if($admin->isSystemSuperAdmin())
                                    <span class="badge bg-dark ms-1" title="Protected by system configuration">
                                        <i class="fas fa-lock"></i> System
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input toggle-active" 
                                           type="checkbox" 
                                           data-id="{{ $admin->id }}"
                                           {{ $admin->is_active ? 'checked' : '' }}
                                           {{ $admin->id === auth('admin')->id() || $admin->isSystemSuperAdmin() ? 'disabled' : '' }}>
                                    <label class="form-check-label">
                                        {{ $admin->is_active ? 'Active' : 'Inactive' }}
                                    </label>
                                </div>
                            </td>
                            <td>
                                {{ $admin->last_login_at ? $admin->last_login_at->diffForHumans() : 'Never' }}
                            </td>
                            <td>
                                @if($admin->isSystemSuperAdmin())
                                    <button class="btn btn-sm btn-outline-secondary" disabled title="System super admin cannot be edited">
                                        <i class="fas fa-lock"></i> Protected
                                    </button>
                                @else
                                    <a href="{{ route('admin.users.edit', $admin) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Permission Reference</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h6 class="text-danger">Super Admin</h6>
                    <ul class="small">
                        <li>Full system access</li>
                        <li>Manage other admins</li>
                        <li>Delete data</li>
                        <li>System configuration</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="text-warning">Admin</h6>
                    <ul class="small">
                        <li>Manage tests & results</li>
                        <li>Manage email accounts</li>
                        <li>Manage providers</li>
                        <li>View logs</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="text-secondary">Viewer</h6>
                    <ul class="small">
                        <li>View dashboard</li>
                        <li>View tests & results</li>
                        <li>Read-only access</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.toggle-active').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const adminId = this.dataset.id;
        const checkbox = this;
        const label = this.nextElementSibling;
        
        fetch(`/admin/users/${adminId}/toggle-active`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                label.textContent = data.is_active ? 'Active' : 'Inactive';
            } else {
                // Revert checkbox state
                checkbox.checked = !checkbox.checked;
                alert(data.message || 'An error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Revert checkbox state
            checkbox.checked = !checkbox.checked;
            alert('An error occurred while updating status');
        });
    });
});
</script>
@endpush
@endsection
@extends('layouts.admin')

@section('title', 'Edit Admin User')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Edit Admin User</h1>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.users.update', $admin) }}" method="POST" id="editUserForm">
                @csrf
                @method('PUT')
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" value="{{ $admin->name }}" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="text" class="form-control" value="{{ $admin->email }}" disabled>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="role" class="form-label">Role *</label>
                        <select name="role" id="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="viewer" {{ $admin->role === 'viewer' ? 'selected' : '' }}>
                                Viewer (Read-only)
                            </option>
                            <option value="admin" {{ $admin->role === 'admin' ? 'selected' : '' }}>
                                Admin (Manage)
                            </option>
                            <option value="super_admin" {{ $admin->role === 'super_admin' ? 'selected' : '' }}>
                                Super Admin (Full Access)
                            </option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        
                        @if($admin->id === auth('admin')->id() && $admin->role === 'super_admin')
                            <small class="text-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                You cannot change your own super admin role
                            </small>
                        @endif
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <div class="form-check form-switch mt-2">
                            @if($admin->id === auth('admin')->id())
                                <input type="hidden" name="is_active" value="1">
                                <input class="form-check-input" type="checkbox" id="is_active" checked disabled>
                            @else
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                       {{ $admin->is_active ? 'checked' : '' }}>
                            @endif
                            <label class="form-check-label" for="is_active">
                                Active Account
                            </label>
                        </div>
                        
                        @if($admin->id === auth('admin')->id())
                            <small class="text-muted">You cannot deactivate your own account</small>
                        @endif
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Additional Permissions</label>
                    <small class="text-muted d-block mb-2">
                        Grant specific permissions beyond the default role permissions
                    </small>
                    
                    <div class="row" id="permissions-container">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" 
                                       value="view_all" id="perm_view_all"
                                       {{ in_array('view_all', $admin->permissions ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label" for="perm_view_all">
                                    View All Content
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" 
                                       value="manage_tests" id="perm_manage_tests"
                                       {{ in_array('manage_tests', $admin->permissions ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label" for="perm_manage_tests">
                                    Manage Tests
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" 
                                       value="manage_email_accounts" id="perm_manage_email_accounts"
                                       {{ in_array('manage_email_accounts', $admin->permissions ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label" for="perm_manage_email_accounts">
                                    Manage Email Accounts
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" 
                                       value="manage_providers" id="perm_manage_providers"
                                       {{ in_array('manage_providers', $admin->permissions ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label" for="perm_manage_providers">
                                    Manage Providers
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" 
                                       value="view_logs" id="perm_view_logs"
                                       {{ in_array('view_logs', $admin->permissions ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label" for="perm_view_logs">
                                    View Logs
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" 
                                       value="delete_data" id="perm_delete_data"
                                       {{ in_array('delete_data', $admin->permissions ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label" for="perm_delete_data">
                                    <span class="text-danger">Delete Data</span>
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" 
                                       value="manage_admins" id="perm_manage_admins"
                                       {{ in_array('manage_admins', $admin->permissions ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label" for="perm_manage_admins">
                                    <span class="text-danger">Manage Admins</span>
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" 
                                       value="run_commands" id="perm_run_commands"
                                       {{ in_array('run_commands', $admin->permissions ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label" for="perm_run_commands">
                                    <span class="text-danger">Run Commands</span>
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" 
                                       value="system_config" id="perm_system_config"
                                       {{ in_array('system_config', $admin->permissions ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label" for="perm_system_config">
                                    <span class="text-danger">System Configuration</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <h6>Current Effective Permissions:</h6>
                    <div id="effective-permissions">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
const rolePermissions = {
    'super_admin': [
        'view_all', 'manage_tests', 'manage_email_accounts', 'manage_providers',
        'manage_admins', 'delete_data', 'view_logs', 'run_commands', 'system_config'
    ],
    'admin': [
        'view_all', 'manage_tests', 'manage_email_accounts', 'manage_providers', 'view_logs'
    ],
    'viewer': ['view_all']
};

function updateEffectivePermissions() {
    const role = document.getElementById('role').value;
    const basePerms = rolePermissions[role] || [];
    
    // Get additional checked permissions
    const checkedPerms = Array.from(document.querySelectorAll('#permissions-container input:checked'))
        .map(cb => cb.value);
    
    // Combine and deduplicate
    const allPerms = [...new Set([...basePerms, ...checkedPerms])];
    
    // Update display
    const container = document.getElementById('effective-permissions');
    if (role === 'super_admin') {
        container.innerHTML = '<span class="badge bg-danger">Full System Access</span>';
        // Don't disable checkboxes, just visually indicate they're not needed
        document.querySelectorAll('#permissions-container input').forEach(cb => {
            cb.disabled = false;
            cb.parentElement.style.opacity = '0.5';
        });
    } else {
        container.innerHTML = allPerms.map(perm => {
            const isBase = basePerms.includes(perm);
            const label = perm.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            const badge = isBase ? 'bg-secondary' : 'bg-success';
            const source = isBase ? ' (role)' : ' (custom)';
            return `<span class="badge ${badge} me-1">${label}${source}</span>`;
        }).join(' ');
        
        // Enable permission checkboxes and restore opacity
        document.querySelectorAll('#permissions-container input').forEach(cb => {
            cb.disabled = false;
            cb.parentElement.style.opacity = '1';
        });
    }
}

// Update on role change
document.getElementById('role').addEventListener('change', updateEffectivePermissions);

// Update on permission change
document.querySelectorAll('#permissions-container input').forEach(cb => {
    cb.addEventListener('change', updateEffectivePermissions);
});

// Initial update
updateEffectivePermissions();

// Add form submission debug
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    console.log('Form is being submitted');
    
    // Check if there's any preventDefault elsewhere
    const role = document.getElementById('role').value;
    console.log('Selected role:', role);
    
    // Allow form submission
    return true;
});

// Debug: Log when page loads
console.log('Edit user form loaded for user ID: {{ $admin->id }}');
</script>
@endpush
@endsection
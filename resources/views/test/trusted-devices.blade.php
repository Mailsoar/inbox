@extends('layouts.app')

@section('title', __('messages.devices.page_title') . ' - Inbox by MailSoar')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-laptop fa-3x text-primary mb-3"></i>
                        <h2 class="h3 fw-bold mb-2">{{ __('messages.devices.title') }}</h2>
                        <p class="text-muted">{{ __('messages.devices.subtitle', ['email' => $email]) }}</p>
                    </div>

                    @if($devices->isEmpty())
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>
                            {{ __('messages.devices.no_devices') }}
                        </div>
                    @else
                        <div class="list-group">
                            @foreach($devices as $device)
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        @if($device->browser)
                                            <i class="fab fa-{{ strtolower($device->browser) }} me-2"></i>
                                        @else
                                            <i class="fas fa-desktop me-2"></i>
                                        @endif
                                        {{ $device->display_name }}
                                        @if($currentToken && $device->token === $currentToken)
                                            <span class="badge bg-success ms-2">{{ __('messages.devices.current_device') }}</span>
                                        @endif
                                    </h6>
                                    <p class="mb-0 text-muted small">
                                        <i class="fas fa-clock me-1"></i>
                                        {{ __('messages.devices.last_used') }}: {{ $device->last_used_at->diffForHumans() }}
                                        <br>
                                        <i class="fas fa-network-wired me-1"></i>
                                        IP: {{ $device->ip_address }}
                                        <br>
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        {{ __('messages.devices.expires') }}: {{ $device->expires_at->format('d/m/Y') }}
                                    </p>
                                </div>
                                <div>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="removeDevice({{ $device->id }}, '{{ $device->display_name }}')"
                                            @if($currentToken && $device->token === $currentToken) 
                                                data-current="true"
                                            @endif>
                                        <i class="fas fa-trash"></i>
                                        {{ __('messages.devices.remove') }}
                                    </button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4 text-center">
                        <a href="{{ route('test.my-tests-authenticated') }}" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>
                            {{ __('messages.general.back') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function removeDevice(deviceId, deviceName) {
    const isCurrent = event.target.getAttribute('data-current') === 'true';
    const message = isCurrent 
        ? '{{ __("messages.devices.confirm_remove_current") }}' 
        : '{{ __("messages.devices.confirm_remove") }}';
    
    if (confirm(message.replace(':device', deviceName))) {
        fetch(`/my-tests/devices/${deviceId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.logout) {
                    // Si c'était l'appareil actuel, rediriger vers la page de connexion
                    window.location.href = '{{ route("test.request-access") }}';
                } else {
                    // Recharger la page pour mettre à jour la liste
                    window.location.reload();
                }
            } else {
                alert('{{ __("messages.devices.error_removing") }}');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('{{ __("messages.devices.error_removing") }}');
        });
    }
}
</script>
@endpush
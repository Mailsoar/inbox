@extends('layouts.app')

@section('title', __('messages.home.page_title') . ' - MailSoar')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-5">
                <h1 class="display-4">{{ __('messages.home.title') }}</h1>
                <p class="lead">{{ __('messages.home.subtitle') }}</p>
            </div>

            <div class="card shadow">
                <div class="card-body p-4">
                    <form id="testForm">
                        <div class="mb-4">
                            <label for="email" class="form-label">{{ __('messages.home.email_label') }}</label>
                            <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                   placeholder="{{ __('messages.home.email_placeholder') }}" required>
                            <div class="form-text">{{ __('messages.home.email_help') }}</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">{{ __('messages.home.audience_type') }}</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="audience_type" id="b2c" value="b2c" checked>
                                <label class="btn btn-outline-primary" for="b2c">
                                    <i class="fas fa-user"></i> {{ __('messages.home.audience_b2c') }}
                                </label>

                                <input type="radio" class="btn-check" name="audience_type" id="b2b" value="b2b">
                                <label class="btn btn-outline-primary" for="b2b">
                                    <i class="fas fa-building"></i> {{ __('messages.home.audience_b2b') }}
                                </label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">{{ __('messages.home.test_type') }}</label>
                            <select class="form-select" id="test_type" name="test_type">
                                <option value="standard">{{ __('messages.home.test_standard') }}</option>
                                <option value="specific_provider">{{ __('messages.home.test_provider') }}</option>
                                <option value="specific_antispam">{{ __('messages.home.test_antispam') }}</option>
                            </select>
                        </div>

                        <div class="mb-4 d-none" id="specific_target_group">
                            <label for="specific_target" class="form-label">{{ __('messages.home.specific_target') }}</label>
                            <select class="form-select" id="specific_target" name="specific_target">
                                <!-- Options populated by JavaScript -->
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="fas fa-paper-plane"></i> {{ __('messages.home.start_test') }}
                            </button>
                        </div>

                        @if(isset($emailRemaining) || isset($ipRemaining))
                        <div class="mt-3 text-center text-muted small">
                            @if(isset($emailRemaining))
                                <div>{{ __('messages.home.tests_remaining_email') }} : {{ $emailRemaining }}/{{ config('mailsoar.rate_limit_per_email') }}</div>
                            @endif
                            @if(isset($ipRemaining))
                                <div>{{ __('messages.home.tests_remaining_ip') }} : {{ $ipRemaining }}/{{ config('mailsoar.rate_limit_per_ip') }}</div>
                            @endif
                        </div>
                        @endif
                    </form>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="#" data-bs-toggle="modal" data-bs-target="#myTestsModal">
                    <i class="fas fa-history"></i> {{ __('messages.home.find_tests') }}
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour retrouver les tests -->
<div class="modal fade" id="myTestsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('messages.home.find_tests') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('test.request-access') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="find_email" class="form-label">{{ __('messages.home.email_label') }}</label>
                        <input type="email" class="form-control" id="find_email" name="email" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('messages.general.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('messages.general.search') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de résultat -->
<div class="modal fade" id="resultModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('messages.home.test_created') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success">
                    <h6 class="alert-heading">{{ __('messages.home.instructions') }} :</h6>
                    <p class="mb-0" id="instructions"></p>
                </div>
                
                <h6 class="mt-4">{{ __('messages.home.test_addresses') }} :</h6>
                <div id="emailList" class="list-group mb-3">
                    <!-- Populated by JavaScript -->
                </div>
                
                <div class="text-center">
                    <a href="#" id="viewResultsBtn" class="btn btn-primary">
                        <i class="fas fa-chart-line"></i> {{ __('messages.home.view_results') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const providers = @json(array_map(fn($p) => ['value' => $p, 'label' => config("mailsoar.providers.$p.name")], array_keys(config('mailsoar.providers'))));
const antispamFilters = @json(array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(config('mailsoar.antispam_filters')), config('mailsoar.antispam_filters')));

document.getElementById('test_type').addEventListener('change', function() {
    const targetGroup = document.getElementById('specific_target_group');
    const targetSelect = document.getElementById('specific_target');
    
    if (this.value === 'standard') {
        targetGroup.classList.add('d-none');
        targetSelect.removeAttribute('required');
    } else {
        targetGroup.classList.remove('d-none');
        targetSelect.setAttribute('required', 'required');
        
        // Populate options
        targetSelect.innerHTML = '<option value="">Sélectionnez...</option>';
        const options = this.value === 'specific_provider' ? providers : antispamFilters;
        
        options.forEach(opt => {
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.label;
            targetSelect.appendChild(option);
        });
    }
});

document.getElementById('testForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __('messages.general.loading') }}...';
    
    try {
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        
        const response = await axios.post('{{ route("test.create") }}', data);
        
        if (response.data.success) {
            // Show results modal
            document.getElementById('instructions').textContent = response.data.instructions;
            
            const emailList = document.getElementById('emailList');
            emailList.innerHTML = '';
            
            response.data.email_accounts.forEach(account => {
                const item = document.createElement('div');
                item.className = 'list-group-item';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${account.email}</strong>
                            <span class="badge bg-secondary ms-2">${account.provider}</span>
                        </div>
                        <small class="text-muted">${account.name || ''}</small>
                    </div>
                `;
                emailList.appendChild(item);
            });
            
            document.getElementById('viewResultsBtn').href = response.data.redirect_url;
            
            const modal = new bootstrap.Modal(document.getElementById('resultModal'));
            modal.show();
            
            // Reset form
            this.reset();
        }
    } catch (error) {
        let message = 'Une erreur est survenue';
        
        if (error.response) {
            if (error.response.status === 429) {
                message = error.response.data.error;
            } else if (error.response.data.errors) {
                message = Object.values(error.response.data.errors).flat().join('\n');
            } else if (error.response.data.error) {
                message = error.response.data.error;
            }
        }
        
        alert(message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});
</script>
@endpush
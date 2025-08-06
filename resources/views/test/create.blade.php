@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h2 class="text-center mb-4">{{ __('messages.test.title') }}</h2>
            
            <!-- Progress Steps -->
            <div class="mb-5">
                <div class="d-flex justify-content-between">
                    <div class="text-center flex-fill">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2" 
                             style="width: 40px; height: 40px;" id="step1-circle">
                            <span>1</span>
                        </div>
                        <p class="small mb-0">{{ __('messages.test.step_configuration') }}</p>
                    </div>
                    <div class="text-center flex-fill">
                        <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-2" 
                             style="width: 40px; height: 40px;" id="step2-circle">
                            <span>2</span>
                        </div>
                        <p class="small mb-0">{{ __('messages.test.step_instructions') }}</p>
                    </div>
                    <div class="text-center flex-fill">
                        <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-2" 
                             style="width: 40px; height: 40px;">
                            <span>3</span>
                        </div>
                        <p class="small mb-0">{{ __('messages.test.step_results') }}</p>
                    </div>
                </div>
            </div>

            <!-- Error Alert Container -->
            <div id="errorAlert" class="alert alert-danger alert-dismissible fade show d-none" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <span id="errorMessage"></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <div class="card shadow">
                <div class="card-body p-4">
                    <form id="testForm">
                        @csrf
                        
                        <!-- Step 1: Configuration -->
                        <div id="step1" class="form-step">
                            <h4 class="mb-4">{{ __('messages.test.configure_title') }}</h4>
                            
                            <!-- Visitor Email -->
                            <div class="mb-4">
                                <label for="visitor_email" class="form-label fw-bold">
                                    <i class="fas fa-envelope"></i> {{ __('messages.home.email_label') }}
                                </label>
                                <input type="email" 
                                       name="visitor_email" 
                                       id="visitor_email" 
                                       class="form-control form-control-lg @error('visitor_email') is-invalid @enderror" 
                                       placeholder="{{ __('messages.home.email_placeholder') }}"
                                       value="{{ old('visitor_email', $prefilledEmail ?? '') }}"
                                       @if($prefilledEmail) readonly @endif
                                       required>
                                <small class="text-muted">
                                    {{ __('messages.test.email_help') }}
                                </small>
                                @if($prefilledEmail)
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-success">
                                        <i class="fas fa-check-circle"></i> {{ __('messages.test.authenticated_user') }}
                                    </small>
                                    <a href="{{ route('test.logout') }}" class="btn btn-sm btn-outline-danger" 
                                       onclick="event.preventDefault(); if(confirm('{{ __('messages.general.confirm_logout') }}')) { document.getElementById('logout-form').submit(); }">
                                        <i class="fas fa-sign-out-alt"></i> {{ __('messages.my_tests.logout') }}
                                    </a>
                                </div>
                                <form id="logout-form" action="{{ route('test.logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                                @endif
                                <small class="text-info" id="emailLimitInfo" style="display: none;">
                                    <i class="fas fa-info-circle"></i> <span id="emailLimitText"></span>
                                </small>
                                @error('visitor_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <!-- Audience Type -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-users"></i> {{ __('messages.home.audience_type') }}
                                </label>
                                <p class="text-muted small mb-3">
                                    {{ __('messages.test.audience_help') }}
                                </p>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="audience-card">
                                            <input type="radio" name="audience_type" 
                                                   id="audience_b2c" value="b2c" checked class="d-none">
                                            <label for="audience_b2c" class="card p-3 h-100 mb-0">
                                                <div class="text-center">
                                                    <i class="fas fa-user mb-2" style="font-size: 2rem; color: #007bff;"></i>
                                                    <h6 class="mb-1">{{ __('messages.home.audience_b2c') }}</h6>
                                                    <p class="small text-muted mb-0">
                                                        {{ __('messages.test.b2c_desc') }}<br>
                                                        (Gmail, Yahoo, etc.)
                                                    </p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="audience-card">
                                            <input type="radio" name="audience_type" 
                                                   id="audience_b2b" value="b2b" class="d-none">
                                            <label for="audience_b2b" class="card p-3 h-100 mb-0">
                                                <div class="text-center">
                                                    <i class="fas fa-building mb-2" style="font-size: 2rem; color: #28a745;"></i>
                                                    <h6 class="mb-1">{{ __('messages.home.audience_b2b') }}</h6>
                                                    <p class="small text-muted mb-0">
                                                        {{ __('messages.test.b2b_desc') }}<br>
                                                        (Office 365, G Suite)
                                                    </p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="audience-card">
                                            <input type="radio" name="audience_type" 
                                                   id="audience_mixed" value="mixed" class="d-none">
                                            <label for="audience_mixed" class="card p-3 h-100 mb-0">
                                                <div class="text-center">
                                                    <i class="fas fa-users mb-2" style="font-size: 2rem; color: #ffc107;"></i>
                                                    <h6 class="mb-1">{{ __('messages.test.mixed_audience') }}</h6>
                                                    <p class="small text-muted mb-0">
                                                        {{ __('messages.test.mixed_desc') }}<br>
                                                        {{ __('messages.test.combined_audience') }}
                                                    </p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Continue Button -->
                            <div class="d-grid">
                                <button type="button" class="btn btn-primary btn-lg" onclick="proceedToStep2()">
                                    <i class="fas fa-arrow-right"></i> Continue to Instructions
                                </button>
                            </div>
                            
                        </div>

                        <!-- Step 2: Instructions -->
                        <div id="step2" class="form-step" style="display: none;">
                            <div class="text-center mb-4">
                                <div class="spinner-border text-primary mb-3" role="status" id="loadingSpinner">
                                    <span class="visually-hidden">Creating test...</span>
                                </div>
                                <p class="text-muted" id="loadingText">Creating your test...</p>
                            </div>

                            <div id="instructionsContent" style="display: none;">
                                <h4 class="mb-4">{{ __("messages.test.send_test_email") }}</h4>
                                
                                <!-- Test ID -->
                                <div class="alert alert-info mb-4">
                                    <h5 class="alert-heading">
                                        <i class="fas fa-key"></i> {{ __("messages.test.your_test_id") }}
                                    </h5>
                                    <div class="d-flex align-items-center justify-content-center my-3">
                                        <h2 class="mb-0 font-monospace text-primary me-3" id="testId">-</h2>
                                        <button type="button" class="btn btn-outline-primary" onclick="copyTestId()">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                    <p class="mb-0 text-center">
                                        <small>Include this ID in your email subject or body</small>
                                    </p>
                                </div>

                                <!-- Timer Warning -->
                                <div class="alert alert-warning mb-4">
                                    <i class="fas fa-clock"></i> 
                                    <strong>Time Limit:</strong> You have <span id="timeRemaining">{{ config('mailsoar.email_check_timeout_minutes', 30) }} minutes</span> 
                                    to send your test email before this test expires.
                                </div>

                                <!-- Seed List -->
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">
                                                <i class="fas fa-list"></i> {{ __("messages.test.test_email_addresses") }} (<span id="seedCount">0</span>)
                                            </h5>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="copyAllEmails()">
                                                    <i class="fas fa-copy"></i> Copy All
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="downloadCSV()">
                                                    <i class="fas fa-download"></i> Download CSV
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                        <div id="seedList" class="font-monospace small">
                                            <!-- Seeds will be loaded here -->
                                        </div>
                                    </div>
                                </div>

                                <!-- Instructions -->
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Instructions</h5>
                                    </div>
                                    <div class="card-body">
                                        <ol class="mb-0">
                                            <li class="mb-2">From your email service provider (ESP), create a new email campaign</li>
                                            <li class="mb-2">Add <strong>ALL</strong> the test email addresses above as recipients</li>
                                            <li class="mb-2">
                                                <strong>Important:</strong> Include this test ID <code class="text-danger" id="testIdInline">-</code> in either:
                                                <ul>
                                                    <li>The subject line, OR</li>
                                                    <li>The email body</li>
                                                </ul>
                                            </li>
                                            <li>Send your email within the next <strong id="timeRemainingInline">-</strong> minutes</li>
                                        </ol>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="row">
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-danger btn-lg w-100" onclick="cancelTest()">
                                            <i class="fas fa-times"></i> Cancel Test
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-primary btn-lg w-100" onclick="viewResults()">
                                            <i class="fas fa-chart-line"></i> View Results
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden fields -->
                        <input type="hidden" name="test_id" id="test_id">
                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
                    </form>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-shield-alt text-success mb-2" style="font-size: 2rem;"></i>
                        <h6>Secure</h6>
                        <p class="small text-muted">Your data is automatically deleted after 7 days</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-bolt text-warning mb-2" style="font-size: 2rem;"></i>
                        <h6>Fast</h6>
                        <p class="small text-muted">Get results in real-time as emails arrive</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <i class="fas fa-chart-line text-info mb-2" style="font-size: 2rem;"></i>
                        <h6>Detailed</h6>
                        <p class="small text-muted">Complete SPF/DKIM/DMARC analysis</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Confirmation Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelModalLabel">
                    <i class="fas fa-exclamation-triangle text-warning"></i> Cancel Test
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this test?</p>
                <p class="text-muted">This action cannot be undone. You will need to create a new test.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left"></i> Go Back
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmCancel()" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Yes, Cancel Test
                </button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.audience-card label {
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid #dee2e6;
}

.audience-card label:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.audience-card input[type="radio"]:checked + label {
    border-color: #007bff;
    background-color: #e7f3ff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

.audience-card input[type="radio"]:checked + label h6 {
    color: #007bff;
}

.font-monospace {
    font-family: 'Courier New', Courier, monospace;
}

#seedList {
    line-height: 1.8;
}

.form-step {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

input[readonly] {
    background-color: #e9ecef !important;
    cursor: not-allowed;
}
</style>
@endpush

@push('scripts')
<script>
let testData = null;
let countdownInterval = null;
let emailCheckTimeout = null;

// Check email limits when user types
document.getElementById('visitor_email').addEventListener('input', function(e) {
    const email = e.target.value;
    
    // Clear previous timeout
    if (emailCheckTimeout) {
        clearTimeout(emailCheckTimeout);
    }
    
    // Hide info if email is invalid
    if (!validateEmail(email)) {
        document.getElementById('emailLimitInfo').style.display = 'none';
        return;
    }
    
    // Debounce the check
    emailCheckTimeout = setTimeout(() => {
        checkEmailLimits(email);
    }, 500);
});

function checkEmailLimits(email) {
    axios.post('{{ route("test.check-limits") }}', { email: email })
        .then(response => {
            const data = response.data;
            if (data.valid) {
                const info = document.getElementById('emailLimitInfo');
                const text = document.getElementById('emailLimitText');
                const continueBtn = document.querySelector('button[onclick="proceedToStep2()"]');
                
                if (data.remaining === 0) {
                    text.textContent = `Daily limit reached (${data.limit} tests per day). Please use a different email.`;
                    info.className = 'text-danger';
                    // Disable the continue button
                    if (continueBtn) {
                        // TEMPORARILY COMMENT OUT FOR TESTING
                        // continueBtn.disabled = true;
                        // continueBtn.classList.add('disabled');
                        
                        // Add warning text instead
                        continueBtn.textContent = '{{ __("messages.test.continue_anyway") }}';
                        continueBtn.classList.add('btn-danger');
                        continueBtn.classList.remove('btn-primary');
                    }
                } else {
                    // Enable the continue button
                    if (continueBtn) {
                        continueBtn.disabled = false;
                        continueBtn.classList.remove('disabled');
                    }
                    
                    if (data.remaining <= 3) {
                        text.textContent = `Only ${data.remaining} tests remaining today (${data.limit} per day).`;
                        info.className = 'text-warning';
                    } else {
                        text.textContent = `${data.remaining} tests remaining today (${data.limit} per day).`;
                        info.className = 'text-info';
                    }
                }
                
                info.style.display = 'block';
            }
        })
        .catch(error => {
        });
}

function proceedToStep2() {
    // Validate step 1
    const email = document.getElementById('visitor_email').value;
    if (!email || !validateEmail(email)) {
        showError('{{ __("messages.test.invalid_email") }}');
        return;
    }

    // Update progress
    document.getElementById('step1-circle').classList.remove('bg-primary');
    document.getElementById('step1-circle').classList.add('bg-secondary');
    document.getElementById('step2-circle').classList.remove('bg-secondary');
    document.getElementById('step2-circle').classList.add('bg-primary');

    // Hide step 1, show step 2
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';

    // Create the test
    createTest();
}

function createTest() {
    try {
        const data = {
            visitor_email: document.getElementById('visitor_email').value,
            audience_type: document.querySelector('input[name="audience_type"]:checked').value,
            _token: '{{ csrf_token() }}'
        };

        const url = '{{ route("test.store") }}';
        
        if (typeof axios === 'undefined') {
            showError('Error: Axios library not loaded. Please refresh the page.');
            goBackToStep1();
            return;
        }
        
        const loadingText = document.getElementById('loadingText');
        if (loadingText) {
            loadingText.textContent = '{{ __("messages.test.creating_test_wait") }}';
        }
        
        axios.post(url, data, {
            timeout: 30000,
            validateStatus: function (status) {
                return true;
            }
        })
            .then(response => {
                if (response.status === 429 || 
                    (response.status === 422 && response.data.error_code === 'RATE_LIMIT_EXCEEDED') ||
                    (response.status === 200 && response.data.is_rate_limited === true)) {
                    
                    const data = response.data;
                    
                    showError(data.message || '{{ __("messages.test.rate_limit_exceeded") }}');
                    goBackToStep1();
                    return;
                }
                
                const responseData = response.data;
                
                if (responseData.success) {
                    testData = responseData;
                    displayInstructions(responseData);
                    startCountdown(responseData.timeout_minutes || {{ config('mailsoar.email_check_timeout_minutes', 30) }});
                } else {
                    showError(responseData.message || '{{ __("messages.test.error_creating") }}');
                    goBackToStep1();
                }
            })
            .catch(error => {
                if (error.code === 'ECONNABORTED') {
                    showError('{{ __("messages.test.request_timeout") }}');
                } else if (error.request) {
                    
                    // Check if we can parse the response text
                    if (error.request.responseText) {
                        try {
                            const data = JSON.parse(error.request.responseText);
                            if (data.message) {
                                showError(data.message);
                                goBackToStep1();
                                return;
                            }
                        } catch (e) {
                        }
                    }
                    
                    showError('{{ __("messages.test.no_response") }}');
                } else {
                    showError('Error: ' + error.message);
                }
                goBackToStep1();
            });
    } catch (error) {
        showError('{{ __("messages.test.error_creating") }}: ' + error.message);
        goBackToStep1();
    }
}

function goBackToStep1() {
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = 'block';
    document.getElementById('step1-circle').classList.add('bg-primary');
    document.getElementById('step1-circle').classList.remove('bg-secondary');
    document.getElementById('step2-circle').classList.add('bg-secondary');
    document.getElementById('step2-circle').classList.remove('bg-primary');
    document.getElementById('loadingSpinner').style.display = 'block';
    document.getElementById('loadingText').style.display = 'block';
    document.getElementById('instructionsContent').style.display = 'none';
}

function displayInstructions(data) {
    try {
        // Hide loading, show content
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('loadingText').style.display = 'none';
        document.getElementById('instructionsContent').style.display = 'block';

        // Set test ID
        document.getElementById('test_id').value = data.test_id;
        document.getElementById('testId').textContent = data.test_id;
        document.getElementById('testIdInline').textContent = data.test_id;

        // Display seed list
        const seedList = document.getElementById('seedList');
        const seedCount = document.getElementById('seedCount');
        
        let seedHTML = '';
        let count = 0;
        
        if (data.seed_emails && data.seed_emails.length > 0) {
            // Group by provider and account type
            const grouped = data.seed_emails.reduce((acc, email) => {
                const provider = email.provider || 'other';
                if (!acc[provider]) acc[provider] = [];
                acc[provider].push(email);
                return acc;
            }, {});

            // Display grouped with account type badges
            for (const [provider, emails] of Object.entries(grouped)) {
                seedHTML += `<div class="mb-3">`;
                seedHTML += `<strong class="text-muted">${provider.charAt(0).toUpperCase() + provider.slice(1)} (${emails.length}):</strong><br>`;
                emails.forEach(emailData => {
                    const typeColor = emailData.type === 'b2c' ? 'primary' : 
                                    emailData.type === 'b2b' ? 'success' : 'secondary';
                    const typeLabel = emailData.type ? emailData.type.toUpperCase() : 'MIXED';
                    
                    seedHTML += `<div class="d-flex justify-content-between align-items-center">`;
                    seedHTML += `<span>${emailData.email}</span>`;
                    seedHTML += `<span class="badge bg-${typeColor} ms-2">${typeLabel}</span>`;
                    seedHTML += `</div>`;
                    count++;
                });
                seedHTML += `</div>`;
            }
        } else {
            seedHTML = '<div class="text-warning">No test emails available. Please try again.</div>';
        }

        seedList.innerHTML = seedHTML;
        seedCount.textContent = count;
    } catch (error) {
        showError('{{ __("messages.test.error_display") }}: ' + error.message);
    }
}

function startCountdown(minutes) {
    let timeLeft = minutes * 60; // Convert to seconds
    
    function updateDisplay() {
        const mins = Math.floor(timeLeft / 60);
        const secs = timeLeft % 60;
        const display = `${mins}:${secs.toString().padStart(2, '0')}`;
        
        document.getElementById('timeRemaining').textContent = display;
        document.getElementById('timeRemainingInline').textContent = mins + ' minutes';
        
        if (timeLeft <= 0) {
            clearInterval(countdownInterval);
            showError('{{ __("messages.test.test_expired") }}');
            setTimeout(() => {
                window.location.href = '{{ route("test.create") }}';
            }, 2000);
        }
        
        timeLeft--;
    }
    
    updateDisplay();
    countdownInterval = setInterval(updateDisplay, 1000);
}

function copyTestId() {
    const testId = document.getElementById('testId').textContent;
    navigator.clipboard.writeText(testId).then(() => {
        showNotification('{{ __("messages.test.test_id_copied") }}');
    });
}

function copyAllEmails() {
    if (!testData || !testData.seed_emails) return;
    
    const emails = testData.seed_emails.map(e => e.email).join(', ');
    navigator.clipboard.writeText(emails).then(() => {
        showNotification('{{ __("messages.test.addresses_copied") }}');
    });
}

function downloadCSV() {
    if (!testData || !testData.seed_emails) return;
    
    // Just emails, one per line
    const emails = testData.seed_emails.map(seed => seed.email).join('\n');
    
    const blob = new Blob([emails], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `mailsoar_test_${testData.test_id}_emails.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function cancelTest() {
    // Show Bootstrap modal instead of confirm
    const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
    modal.show();
}

function confirmCancel() {
    // Send cancel request
    fetch(`/test/${testData.test_id}/cancel`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('{{ __("messages.test.test_cancelled") }}');
            setTimeout(() => {
                window.location.href = '{{ route("test.create") }}';
            }, 1000);
        } else {
            alert('{{ __("messages.test.error_cancelling") }}');
        }
    });
}

function viewResults() {
    if (!testData || !testData.test_id) return;
    
    // Redirect to results page
    window.location.href = `/test/${testData.test_id}/results`;
}

function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function showNotification(message) {
    // Create a temporary notification
    const notification = document.createElement('div');
    notification.className = 'position-fixed top-0 end-0 m-3 alert alert-success alert-dismissible fade show';
    notification.innerHTML = `
        <i class="fas fa-check-circle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function showError(message) {
    // Show error in the dedicated error alert container
    const errorAlert = document.getElementById('errorAlert');
    const errorMessage = document.getElementById('errorMessage');
    
    errorMessage.textContent = message;
    errorAlert.classList.remove('d-none');
    
    // Scroll to top to make sure user sees the error
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Auto-hide after 10 seconds
    setTimeout(() => {
        errorAlert.classList.add('d-none');
    }, 10000);
}

// Clean up on page unload
window.addEventListener('beforeunload', () => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
});
</script>
@endpush

@push('styles')
@endpush

<!-- reCAPTCHA v3 -->
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
<script>
function executeRecaptcha(callback) {
    grecaptcha.ready(function() {
        grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', {action: 'create_test'})
            .then(function(token) {
                document.getElementById('g-recaptcha-response').value = token;
                callback();
            });
    });
}

// Modifier la soumission du formulaire pour inclure reCAPTCHA
const originalCreateTest = window.createTest;
window.createTest = function() {
    const submitBtn = document.getElementById('startTestBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verification...';
    
    executeRecaptcha(function() {
        originalCreateTest();
    });
};
</script>
@endsection
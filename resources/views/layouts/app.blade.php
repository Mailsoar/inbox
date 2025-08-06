<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inbox by MailSoar')</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Flag icons CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/3.5.0/css/flag-icon.min.css">
    
    <style>
        /* Conteneur pour les boutons flottants */
        .floating-buttons-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            z-index: 1050;
        }
        
        /* Style commun pour tous les boutons flottants */
        .floating-btn {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            white-space: nowrap;
        }
        
        .floating-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.15);
        }
        
        /* Responsive pour mobile */
        @media (max-width: 768px) {
            .floating-buttons-container {
                flex-direction: column-reverse;
                right: 20px;
                left: 20px;
                gap: 8px;
            }
            
            .floating-buttons-container .floating-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <i class="fas fa-envelope-open-text"></i> Inbox by MailSoar
            </a>
            
            <!-- Sélecteur de langue -->
            <div class="ms-auto">
                <div class="btn-group" role="group">
                    @php
                        $queryParams = request()->query();
                        $queryParams['lang'] = 'fr';
                        $frUrl = '?' . http_build_query($queryParams);
                        $queryParams['lang'] = 'en';
                        $enUrl = '?' . http_build_query($queryParams);
                    @endphp
                    <a href="{{ $frUrl }}" class="btn btn-sm {{ app()->getLocale() === 'fr' ? 'btn-light' : 'btn-outline-light' }}">
                        <i class="flag-icon flag-icon-fr"></i> FR
                    </a>
                    <a href="{{ $enUrl }}" class="btn btn-sm {{ app()->getLocale() === 'en' ? 'btn-light' : 'btn-outline-light' }}">
                        <i class="flag-icon flag-icon-gb"></i> EN
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main>
        @yield('content')
    </main>

    <!-- Boutons flottants -->
    <div class="floating-buttons-container">
        @php
            $isAuthenticated = session()->has('verified_email') && 
                              session()->has('verified_token') && 
                              session()->has('verified_at');
        @endphp
        
        @if($isAuthenticated)
            <a href="{{ route('test.my-tests-authenticated') }}" 
               class="btn btn-primary floating-btn">
                <i class="fas fa-history me-2"></i>
                {{ __('messages.home.my_tests') }}
            </a>
        @else
            <button type="button" 
                    class="btn btn-primary floating-btn"
                    data-bs-toggle="modal" 
                    data-bs-target="#findTestsModalGlobal">
                <i class="fas fa-history me-2"></i>
                {{ __('messages.home.my_tests') }}
            </button>
        @endif
    </div>
    
    <!-- Modal pour retrouver ses tests (global) -->
    <div class="modal fade" id="findTestsModalGlobal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-history me-2"></i>
                        {{ __('messages.results.find_my_tests') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="text-muted mb-4">
                        {{ __('messages.verification.subtitle') }}
                    </p>
                    <p>
                        <i class="fas fa-shield-alt fa-2x text-primary mb-3"></i>
                    </p>
                    <p class="mb-4">
                        {{ __('messages.modal.verification_code_info') }}
                    </p>
                    <a href="{{ route('test.request-access') }}" class="btn btn-primary btn-lg">
                        <i class="fas fa-lock me-2"></i>
                        {{ __('messages.modal.access_my_tests') }}
                    </a>
                </div>
                <div class="modal-footer justify-content-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        {{ __('messages.modal.unauthorized_access_protection') }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-2">
                        {{ __('messages.footer.copyright', ['year' => date('Y')]) }}
                    </p>
                    <p class="mb-0">
                        {{ __('messages.footer.made_with') }} <i class="fas fa-heart text-danger"></i> {{ __('messages.footer.by') }} <a href="https://mailsoar.com" target="_blank" class="text-decoration-none">MailSoar</a>
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0">
                        <a href="https://mailsoar.com" target="_blank" class="text-decoration-none me-3">
                            <i class="fas fa-globe"></i> {{ __('messages.footer.main_site') }}
                        </a>
                        <a href="/admin/login" class="text-muted text-decoration-none small">
                            <i class="fas fa-cog"></i> {{ __('messages.footer.admin') }}
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Axios for AJAX -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    
    <script>
        // Configure Axios with CSRF token
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        
        // Initialiser les tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
    
    @stack('scripts')
</body>
</html>
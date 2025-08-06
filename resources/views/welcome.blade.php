@extends('layouts.app')

@section('content')
<div class="hero-section bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">{{ __('messages.home.title') }}</h1>
                <p class="lead mb-4">
                    {{ __('messages.home.hero_description') }}
                </p>
                <div class="d-flex gap-3">
                    <a href="{{ route('test.create') }}" class="btn btn-light btn-lg">
                        <i class="fas fa-flask"></i> {{ __('messages.home.start_free_test') }}
                    </a>
                    <a href="#how-it-works" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-info-circle"></i> {{ __('messages.home.how_it_works') }}
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <i class="fas fa-envelope-open-text" style="font-size: 200px; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section statistiques retirée comme demandé -->

<section class="py-5 bg-light" id="how-it-works">
    <div class="container">
        <h2 class="text-center mb-5">{{ __('messages.home.how_it_works') }}</h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="text-center">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <span class="h2 mb-0">1</span>
                    </div>
                    <h4>{{ __('messages.home.how_it_works_step1_title') }}</h4>
                    <p class="text-muted">{{ __('messages.home.how_it_works_step1_desc') }}</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="text-center">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <span class="h2 mb-0">2</span>
                    </div>
                    <h4>{{ __('messages.home.how_it_works_step2_title') }}</h4>
                    <p class="text-muted">{{ __('messages.home.how_it_works_step2_desc') }}</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="text-center">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <span class="h2 mb-0">3</span>
                    </div>
                    <h4>{{ __('messages.home.how_it_works_step3_title') }}</h4>
                    <p class="text-muted">{{ __('messages.home.how_it_works_step3_desc') }}</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" id="features">
    <div class="container">
        <h2 class="text-center mb-5">{{ __('messages.home.features_title') }}</h2>
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="d-flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-inbox text-primary" style="font-size: 2rem;"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5>{{ __('messages.home.feature_placement_title') }}</h5>
                        <p class="text-muted">{{ __('messages.home.feature_placement_desc') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="d-flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shield-alt text-primary" style="font-size: 2rem;"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5>{{ __('messages.home.feature_authentication_title') }}</h5>
                        <p class="text-muted">{{ __('messages.home.feature_authentication_desc') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="d-flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-network-wired text-primary" style="font-size: 2rem;"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5>{{ __('messages.home.feature_ip_title') }}</h5>
                        <p class="text-muted">{{ __('messages.home.feature_ip_desc') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="d-flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-filter text-primary" style="font-size: 2rem;"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5>{{ __('messages.home.feature_antispam_title') }}</h5>
                        <p class="text-muted">{{ __('messages.home.feature_antispam_desc') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2 class="mb-4">{{ __('messages.home.cta_title') }}</h2>
        <p class="lead mb-4">{{ __('messages.home.cta_desc') }}</p>
        <a href="{{ route('test.create') }}" class="btn btn-light btn-lg">
            <i class="fas fa-rocket"></i> {{ __('messages.home.cta_button') }}
        </a>
    </div>
</section>

@endsection

@push('styles')
<style>
.hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
@endpush
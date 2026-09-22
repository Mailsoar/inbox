@extends('layouts.public')

@section('title', __('messages.general.admin_title') . ' - Check')

@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
<div class="max-w-md mx-auto px-4 py-12 sm:py-20">
    <div class="flex justify-center mb-8">
        @include('partials.logo', ['class' => 'h-10 w-auto'])
    </div>

    <div class="card p-6 sm:p-8 shadow-sm">
        <div class="text-center mb-6">
            <div class="w-14 h-14 rounded-full bg-accent/10 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M3 8v8a2 2 0 002 2h14a2 2 0 002-2V8M3 8l9-5 9 5"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold font-heading">{{ __('messages.general.admin_title') }}</h1>
            <p class="text-sm text-muted-foreground mt-2">{{ __('messages.general.admin_subtitle') }}</p>
        </div>

        @if (session('error'))
            <div class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive mb-5">
                {{ session('error') }}
            </div>
        @endif

        <a href="{{ route('admin.auth.google') }}" class="btn-primary btn-lg w-full gap-3">
            {{-- Logo Google, aux couleurs officielles --}}
            <svg class="w-5 h-5 shrink-0" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            {{ __('messages.general.admin_google') }}
        </a>

        <div class="border-t border-border mt-6 pt-6 text-center">
            <a href="{{ route('home') }}" class="btn-outline btn-sm gap-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                </svg>
                {{ __('messages.general.admin_back') }}
            </a>
        </div>
    </div>
</div>
@endsection

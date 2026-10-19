<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-PHNHM8D');</script>
    <!-- End Google Tag Manager -->

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inbox by MailSoar')</title>

    <link rel="canonical" href="{{ url()->full() }}" />
    <link rel="alternate" hreflang="fr" href="{{ url('/') }}?lang=fr" />
    <link rel="alternate" hreflang="en" href="{{ url('/') }}?lang=en" />
    <link rel="alternate" hreflang="x-default" href="{{ url('/') }}?lang=fr" />

    @yield('meta')

    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32" />
    <link rel="icon" href="/favicon-192x192.png" type="image/png" sizes="192x192" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Design de www.mailsoar.com : surcharge du build Tailwind, puis en-tête et pied de page du site --}}
    <link rel="stylesheet" href="/css/mailsoar/inbox-tailwind-override.css?v={{ filemtime(public_path('css/mailsoar/inbox-tailwind-override.css')) }}">
    <link rel="stylesheet" href="/css/mailsoar/inbox-site-chrome.css?v={{ filemtime(public_path('css/mailsoar/inbox-site-chrome.css')) }}">

    @stack('head')
</head>
<body class="min-h-screen bg-mesh">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PHNHM8D"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    @php
        $langParams = request()->query();
        $langParams['lang'] = 'fr';
        $frUrl = '?' . http_build_query($langParams);
        $langParams['lang'] = 'en';
        $enUrl = '?' . http_build_query($langParams);
    @endphp

    <header class="ms-header">
        <div class="ms-header__inner">
            <a class="ms-logo" href="{{ route('home') }}">
                @include('partials.logo', ['class' => ''])
                <span class="hidden sm:inline">Inbox</span>
            </a>
            @php
                $fr = app()->getLocale() === 'fr';
            @endphp
            <div class="ms-header__actions">
                {{-- Bouton d'en-tête de mailsoar.com : même balisage Spectra (uagb) et mêmes classes --}}
                <div class="wp-block-button">
                    <div class="uagb-button__wrapper">
                        <a class="uagb-buttons-repeater wp-block-button__link"
                           href="{{ $fr ? 'https://www.mailsoar.com/fr/contact/' : 'https://meetings-eu1.hubspot.com/lawrence-heslin/site' }}"
                           rel="noopener" target="_blank" role="button">
                            <div class="uagb-button__link">{{ $fr ? 'Être recontacté' : 'Talk to an expert' }}</div>
                            <span class="uagb-button__icon uagb-button__icon-position-after">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" aria-hidden="true" focusable="false"><path d="M438.6 278.6l-160 160C272.4 444.9 264.2 448 256 448s-16.38-3.125-22.62-9.375c-12.5-12.5-12.5-32.75 0-45.25L338.8 288H32C14.33 288 .0016 273.7 .0016 256S14.33 224 32 224h306.8l-105.4-105.4c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0l160 160C451.1 245.9 451.1 266.1 438.6 278.6z"/></svg>
                            </span>
                        </a>
                    </div>
                </div>
                {{-- Sélecteur de langue au format du site : langue courante en gras, puis les autres --}}
                <nav class="ms-lang" aria-label="Language switcher">
                    @if($fr)
                        <span class="ms-lang__current" aria-current="page">FR</span>
                        <a href="{{ $enUrl }}" aria-label="Switch to English">EN</a>
                    @else
                        <span class="ms-lang__current" aria-current="page">EN</span>
                        <a href="{{ $frUrl }}" aria-label="Passer en français">FR</a>
                    @endif
                </nav>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="ms-footer">
        <div class="ms-footer__inner">
            <p>&copy; {{ date('Y') }} <a href="https://www.mailsoar.com" rel="noopener" target="_blank">MailSoar</a></p>
            <p class="flex items-center gap-4">
                <a href="https://www.mailsoar.com/privacy-policy/" rel="noopener" target="_blank">Privacy policy</a>
                <a href="https://www.mailsoar.com/legal-notice/" rel="noopener" target="_blank">Legal notice</a>
                <a href="{{ route('admin.dashboard') }}" rel="nofollow">{{ __('messages.general.login') }}</a>
            </p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>

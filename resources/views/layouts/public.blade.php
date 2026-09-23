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

    <link rel="icon" href="https://www.mailsoar.com/wp-content/uploads/2021/03/cropped-favicon-32x32-1-32x32.png" sizes="32x32" />
    <link rel="icon" href="https://www.mailsoar.com/wp-content/uploads/2021/03/cropped-favicon-32x32-1-192x192.png" sizes="192x192" />
    <link rel="apple-touch-icon" href="https://www.mailsoar.com/wp-content/uploads/2021/03/cropped-favicon-32x32-1-180x180.png" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
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

    <header class="border-b border-border/60 bg-card/70 backdrop-blur sticky top-0 z-40">
        <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-heading font-bold text-lg tracking-tight">
                @include('partials.logo', ['class' => 'h-7 w-auto'])
                <span class="hidden sm:inline">Inbox</span>
            </a>

            <div class="flex items-center gap-2">
                <div class="inline-flex rounded-md border border-border overflow-hidden text-xs font-medium">
                    <a href="{{ $frUrl }}" class="px-2.5 py-1.5 {{ app()->getLocale() === 'fr' ? 'bg-accent text-accent-foreground' : 'bg-card hover:bg-muted' }}">FR</a>
                    <a href="{{ $enUrl }}" class="px-2.5 py-1.5 {{ app()->getLocale() === 'en' ? 'bg-accent text-accent-foreground' : 'bg-card hover:bg-muted' }}">EN</a>
                </div>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-border/60 py-8">
        <div class="max-w-5xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-muted-foreground">
            <p>&copy; {{ date('Y') }} MailSoar</p>
            <div class="flex items-center gap-4">
                <a href="https://www.mailsoar.com" class="hover:text-foreground transition-colors" rel="noopener" target="_blank">mailsoar.com</a>
                {{-- Accès à l'administration : présent mais effacé, il n'a rien
                     à faire dans le parcours d'un visiteur. --}}
                <a href="{{ route('admin.dashboard') }}"
                   class="text-xs text-muted-foreground/60 hover:text-foreground transition-colors"
                   rel="nofollow">{{ __('messages.general.login') }}</a>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>

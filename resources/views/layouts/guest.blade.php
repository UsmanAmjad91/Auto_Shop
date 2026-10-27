<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme-mode="{{ settings('theme.mode', 'auto') }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ settings('general.company_name') }}</title>
    <script>(function () { var m = document.documentElement.dataset.themeMode;
        if (m === 'dark' || (m === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.setAttribute('data-bs-theme', 'dark'); })();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>:root { --as-primary: {{ settings('theme.primary_color', '#1f6feb') }}; }</style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="text-center mb-3">
            @if(settings('general.logo'))
                <img src="{{ asset('storage/'.settings('general.logo')) }}" alt="{{ settings('general.company_name') }}" style="max-height:60px" class="mb-2">
            @else
                <div class="auth-logo"><i class="bi bi-car-front-fill"></i></div>
            @endif
            <h1 class="h5 fw-bold mb-0">{{ settings('general.company_name') }}</h1>
        </div>
        <div class="card shadow-sm"><div class="card-body p-4">
            @include('partials.flash')
            @yield('content')
        </div></div>
        <p class="text-center small text-secondary mt-3">@yield('below')</p>
    </div>
</div>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toasts"></div>
</body>
</html>

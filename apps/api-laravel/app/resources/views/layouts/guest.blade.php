<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Lawet Kebumen</title>

        @vite(['resources/css/app.css'])
    </head>
    <body>
        <div class="page">
            <header class="topbar">
                <div class="brand">
                    <img class="brand__logo" src="{{ asset('logo.png') }}" alt="Lawet Kebumen">
                    <div>
                        <div class="brand__title">Lawet Kebumen</div>
                        <div class="brand__subtitle">Pelayanan Online Disdukcapil Kebumen</div>
                    </div>
                </div>
            </header>

            <main class="auth-shell">
                @yield('content')
            </main>
        </div>
    </body>
</html>

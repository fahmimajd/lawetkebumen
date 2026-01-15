<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="user-id" content="{{ (int) auth()->id() }}">
        <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
        <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.options.host') }}">
        <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.options.port') }}">
        <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.scheme') }}">

        <title>Lawet Kebumen</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
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
                <div class="topbar__actions">
                    <div class="status-pill" data-connection-status>Offline</div>
                    <nav class="nav">
                        <a class="nav__link" href="{{ route('inbox') }}">Inbox</a>
                        <a class="nav__link" href="{{ route('contacts.index') }}">Contacts</a>
                        @can('admin')
                            <a class="nav__link" href="{{ route('settings.users.index') }}">Users</a>
                            <a class="nav__link" href="{{ route('settings.quick-answers.index') }}">Quick Answers</a>
                            <a class="nav__link" href="{{ route('settings.wa.index') }}">WA Connection</a>
                            <a class="nav__link" href="{{ route('reports.daily-agents') }}">Reports</a>
                        @endcan
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="nav__button">Logout</button>
                        </form>
                    </nav>
                </div>
            </header>

            <main class="shell" data-app-root data-api-base="/api">
                @yield('content')
            </main>
        </div>
    </body>
</html>

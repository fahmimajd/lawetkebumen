@extends('layouts.guest')

@section('content')
    <form class="auth-card" method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <div class="auth-card__title">Sign in</div>
            <div class="auth-card__subtitle">Use your account to access the inbox.</div>
        </div>

        <div class="auth-field">
            <label class="auth-label" for="email">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                class="input"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
            >
            @error('email')
                <div class="auth-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label class="auth-label" for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                class="input"
                required
                autocomplete="current-password"
            >
            @error('password')
                <div class="auth-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-actions">
            <button type="submit" class="auth-button">Login</button>
        </div>
    </form>
@endsection

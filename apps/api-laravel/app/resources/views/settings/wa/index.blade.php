@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="panel__header">
            <div class="panel__title">WA Connection</div>
        </div>

        @if (session('status'))
            <div class="status-pill">{{ session('status') }}</div>
        @endif

        <div
            class="wa-card"
            data-wa-connection
            data-status-url="{{ route('settings.wa.status') }}"
            data-qr-url="{{ route('settings.wa.qr') }}"
        >
            <div>
                <div class="wa-card__label">Status</div>
                <div class="wa-card__status" data-wa-status>Checking…</div>
                <div class="wa-card__error" data-wa-error></div>
            </div>
            <div class="wa-card__qr" data-wa-qr-wrap>
                <canvas id="wa-qr-canvas"></canvas>
                <div class="wa-card__hint" data-wa-qr-hint>QR akan muncul jika belum terhubung.</div>
                <div class="wa-card__raw" data-wa-qr-raw></div>
            </div>
            <div class="wa-card__actions">
                <form method="POST" action="{{ route('settings.wa.reconnect') }}">
                    @csrf
                    <button type="submit" class="nav__button">Reconnect</button>
                </form>
                <form method="POST" action="{{ route('settings.wa.logout') }}">
                    @csrf
                    <button type="submit" class="nav__button">Logout</button>
                </form>
                <form method="POST" action="{{ route('settings.wa.reset') }}" onsubmit="return confirm('Hapus koneksi WA dan ganti nomor? QR baru akan diperlukan.');">
                    @csrf
                    <button type="submit" class="nav__button nav__button--danger">Hapus Koneksi</button>
                </form>
            </div>
        </div>
    </section>
@endsection

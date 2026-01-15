@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="panel__header">
            <div class="panel__title">Tambah Kontak</div>
        </div>

        @if (session('status'))
            <div class="status-pill">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="panel__errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('contacts.store') }}" class="panel__form">
            @csrf
            <div class="form-grid">
                <div class="auth-field">
                    <label class="auth-label" for="new-display-name">Nama</label>
                    <input id="new-display-name" name="display_name" class="input" value="{{ old('display_name') }}">
                    @error('display_name')
                        <div class="auth-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="auth-field">
                    <label class="auth-label" for="new-phone">No. HP</label>
                    <input id="new-phone" name="phone" class="input" value="{{ old('phone') }}" required>
                    @error('phone')
                        <div class="auth-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="auth-field">
                    <label class="auth-label" for="new-wa-id">WA ID (opsional)</label>
                    <input id="new-wa-id" name="wa_id" class="input" value="{{ old('wa_id') }}" placeholder="628xxx@s.whatsapp.net">
                    @error('wa_id')
                        <div class="auth-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="auth-field">
                    <label class="auth-label" for="new-avatar">Avatar URL (opsional)</label>
                    <input id="new-avatar" name="avatar_url" type="url" class="input" value="{{ old('avatar_url') }}">
                    @error('avatar_url')
                        <div class="auth-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="auth-actions">
                <button type="submit" class="auth-button">Simpan</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel__header">
            <div class="panel__title">Daftar Kontak</div>
        </div>

        <div class="table">
            <div class="table__row table__row--head table__row--contacts">
                <div>Nama</div>
                <div>No. HP</div>
                <div>WA ID</div>
                <div>Update</div>
                <div>Aksi</div>
            </div>
            @forelse ($contacts as $contact)
                <div class="table__row table__row--contacts">
                    <div>{{ $contact->display_name ?? '-' }}</div>
                    <div>{{ $contact->phone }}</div>
                    <div class="table__wa-id">{{ $contact->wa_id }}</div>
                    <div>{{ $contact->updated_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i') }}</div>
                    <div class="table__actions">
                        <details>
                            <summary class="nav__button">Edit</summary>
                            <form method="POST" action="{{ route('contacts.update', $contact) }}" class="panel__form panel__form--compact">
                                @csrf
                                @method('PUT')
                                <div class="form-grid form-grid--compact">
                                    <input name="display_name" class="input" value="{{ $contact->display_name }}">
                                    <input name="phone" class="input" value="{{ $contact->phone }}" required>
                                    <input name="wa_id" class="input" value="{{ $contact->wa_id }}" required>
                                    <input name="avatar_url" type="url" class="input" value="{{ $contact->avatar_url }}">
                                </div>
                                <div class="auth-actions">
                                    <button type="submit" class="nav__button">Simpan</button>
                                </div>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('contacts.destroy', $contact) }}" onsubmit="return confirm('Hapus kontak ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="nav__button nav__button--danger">Hapus</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="empty-state">Belum ada kontak.</div>
            @endforelse
        </div>
    </section>
@endsection

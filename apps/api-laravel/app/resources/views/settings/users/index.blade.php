@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="panel__header">
            <div class="panel__title">User Management</div>
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

        <form method="POST" action="{{ route('settings.users.store') }}" class="panel__form">
            @csrf
            <div class="form-grid">
                <div class="auth-field">
                    <label class="auth-label" for="new-name">Name</label>
                    <input id="new-name" name="name" class="input" value="{{ old('name') }}" required>
                    @error('name')
                        <div class="auth-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="auth-field">
                    <label class="auth-label" for="new-email">Email</label>
                    <input id="new-email" name="email" type="email" class="input" value="{{ old('email') }}" required>
                    @error('email')
                        <div class="auth-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="auth-field">
                    <label class="auth-label" for="new-role">Role</label>
                    <select id="new-role" name="role" class="input" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role) }}</option>
                        @endforeach
                    </select>
                    @error('role')
                        <div class="auth-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="auth-field">
                    <label class="auth-label" for="new-password">Password</label>
                    <input id="new-password" name="password" type="password" class="input" required>
                    @error('password')
                        <div class="auth-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="auth-actions">
                <button type="submit" class="auth-button">Create User</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel__header">
            <div class="panel__title">Users</div>
        </div>

        <div class="table">
            <div class="table__row table__row--head">
                <div>Name</div>
                <div>Email</div>
                <div>Role</div>
                <div>Status</div>
                <div>Actions</div>
            </div>
            @foreach ($users as $user)
                <div class="table__row">
                    <div>{{ $user->name }}</div>
                    <div>{{ $user->email }}</div>
                    <div>{{ $user->roles->pluck('name')->first() ?? ($user->role?->value ?? '-') }}</div>
                    <div>{{ $user->is_active ? 'Active' : 'Inactive' }}</div>
                    <div class="table__actions">
                        <details>
                            <summary class="nav__button">Edit</summary>
                            <form method="POST" action="{{ route('settings.users.update', $user) }}" class="panel__form panel__form--compact">
                                @csrf
                                @method('PUT')
                                <div class="form-grid form-grid--compact">
                                    <input name="name" class="input" value="{{ $user->name }}" required>
                                    <input name="email" type="email" class="input" value="{{ $user->email }}" required>
                                    <select name="role" class="input" required>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role }}" @selected(($user->role?->value ?? '') === $role)>{{ ucfirst($role) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="auth-actions">
                                    <button type="submit" class="nav__button">Save</button>
                                </div>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('settings.users.toggle', $user) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="nav__button">
                                {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                        @if (auth()->id() !== $user->id)
                            <form method="POST" action="{{ route('settings.users.destroy', $user) }}" onsubmit="return confirm('Hapus user ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="nav__button nav__button--danger">Hapus</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endsection

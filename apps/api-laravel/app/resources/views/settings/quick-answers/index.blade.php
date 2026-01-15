@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="panel__header">
            <div class="panel__title">Quick Answers</div>
        </div>

        <form method="POST" action="{{ route('settings.quick-answers.store') }}" class="panel__form">
            @csrf
            <div class="form-grid">
                <input name="shortcut" class="input" placeholder="/greeting" required>
                <textarea name="body" class="input" rows="3" placeholder="Isi jawaban cepat..." required></textarea>
            </div>
            <input type="hidden" name="is_active" value="0">
            <label class="checkbox">
                <input type="checkbox" name="is_active" value="1" checked>
                Aktif
            </label>
            <div class="auth-actions">
                <button type="submit" class="button button--primary">Simpan</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel__header">
            <div class="panel__title">Daftar Quick Answers</div>
        </div>

        <div class="table">
            <div class="table__row table__row--head table__row--quick-answers">
                <div>Shortcut</div>
                <div>Isi</div>
                <div>Status</div>
                <div>Update</div>
                <div>Aksi</div>
            </div>
            @forelse ($quickAnswers as $quickAnswer)
                <div class="table__row table__row--quick-answers">
                    <div>/{{ $quickAnswer->shortcut }}</div>
                    <div>{{ \Illuminate\Support\Str::limit($quickAnswer->body, 120) }}</div>
                    <div>{{ $quickAnswer->is_active ? 'Active' : 'Inactive' }}</div>
                    <div>{{ $quickAnswer->updated_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i') }}</div>
                    <div class="table__actions">
                        <details>
                            <summary class="nav__button">Edit</summary>
                            <form method="POST" action="{{ route('settings.quick-answers.update', $quickAnswer) }}" class="panel__form panel__form--compact">
                                @csrf
                                @method('PUT')
                                <div class="form-grid form-grid--compact">
                                    <input name="shortcut" class="input" value="/{{ $quickAnswer->shortcut }}" required>
                                    <textarea name="body" class="input" rows="2" required>{{ $quickAnswer->body }}</textarea>
                                </div>
                                <input type="hidden" name="is_active" value="0">
                                <label class="checkbox">
                                    <input type="checkbox" name="is_active" value="1" @checked($quickAnswer->is_active)>
                                    Aktif
                                </label>
                                <div class="auth-actions">
                                    <button type="submit" class="nav__button">Simpan</button>
                                </div>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('settings.quick-answers.destroy', $quickAnswer) }}" onsubmit="return confirm('Hapus quick answer ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="nav__button nav__button--danger">Hapus</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="empty-state">Belum ada quick answer.</div>
            @endforelse
        </div>
    </section>
@endsection

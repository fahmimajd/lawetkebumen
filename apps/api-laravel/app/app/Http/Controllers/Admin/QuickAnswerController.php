<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuickAnswerRequest;
use App\Http\Requests\Admin\UpdateQuickAnswerRequest;
use App\Models\QuickAnswer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuickAnswerController extends Controller
{
    public function index(): View
    {
        $quickAnswers = QuickAnswer::query()
            ->orderBy('shortcut')
            ->get();

        return view('settings.quick-answers.index', [
            'quickAnswers' => $quickAnswers,
        ]);
    }

    public function store(StoreQuickAnswerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        QuickAnswer::create([
            'shortcut' => $this->normalizeShortcut($data['shortcut']),
            'body' => $data['body'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('settings.quick-answers.index')
            ->with('status', 'Quick answer created.');
    }

    public function update(UpdateQuickAnswerRequest $request, QuickAnswer $quickAnswer): RedirectResponse
    {
        $data = $request->validated();

        $quickAnswer->update([
            'shortcut' => $this->normalizeShortcut($data['shortcut']),
            'body' => $data['body'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('settings.quick-answers.index')
            ->with('status', 'Quick answer updated.');
    }

    public function destroy(QuickAnswer $quickAnswer): RedirectResponse
    {
        $quickAnswer->delete();

        return redirect()
            ->route('settings.quick-answers.index')
            ->with('status', 'Quick answer deleted.');
    }

    private function normalizeShortcut(string $shortcut): string
    {
        $shortcut = trim($shortcut);
        $shortcut = ltrim($shortcut, '/');

        return Str::lower($shortcut);
    }
}

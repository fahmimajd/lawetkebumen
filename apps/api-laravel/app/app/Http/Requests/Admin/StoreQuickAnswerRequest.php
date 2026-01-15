<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuickAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shortcut' => ['required', 'string', 'max:50', 'regex:/^\\/?[a-zA-Z0-9_-]+$/', 'unique:quick_answers,shortcut'],
            'body' => ['required', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('shortcut')) {
            $shortcut = trim((string) $this->input('shortcut'));
            $shortcut = ltrim($shortcut, '/');
            $this->merge(['shortcut' => strtolower($shortcut)]);
        }
    }
}

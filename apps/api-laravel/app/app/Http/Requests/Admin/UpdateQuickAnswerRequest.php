<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuickAnswerRequest extends FormRequest
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
        $quickAnswer = $this->route('quickAnswer');

        return [
            'shortcut' => [
                'required',
                'string',
                'max:50',
                'regex:/^\\/?[a-zA-Z0-9_-]+$/',
                Rule::unique('quick_answers', 'shortcut')->ignore($quickAnswer),
            ],
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

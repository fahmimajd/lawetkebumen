<?php

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|Rule>>
     */
    public function rules(): array
    {
        $contactId = $this->route('contact')?->id;

        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'wa_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('contacts', 'wa_id')->ignore($contactId),
            ],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = (string) $this->input('phone', '');
        $waId = (string) $this->input('wa_id', '');
        $displayName = trim((string) $this->input('display_name', ''));
        $avatarUrl = trim((string) $this->input('avatar_url', ''));

        $normalizedPhone = $this->normalizePhone($phone);
        $normalizedWaId = $this->normalizeWaId($waId, $normalizedPhone);

        if ($normalizedPhone === '' && $normalizedWaId !== '') {
            $normalizedPhone = $this->normalizePhone(Str::before($normalizedWaId, '@'));
        }

        $this->merge([
            'phone' => $normalizedPhone,
            'wa_id' => $normalizedWaId,
            'display_name' => $displayName !== '' ? $displayName : null,
            'avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
        ]);
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function normalizeWaId(string $value, string $fallbackPhone): string
    {
        $waId = trim($value);

        if ($waId === '' && $fallbackPhone !== '') {
            $waId = $fallbackPhone;
        }

        if ($waId !== '' && ! str_contains($waId, '@')) {
            $waId .= '@s.whatsapp.net';
        }

        return $waId;
    }
}

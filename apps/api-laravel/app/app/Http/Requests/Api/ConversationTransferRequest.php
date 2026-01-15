<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConversationTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => ['nullable', 'integer', 'exists:users,id', 'required_without:queue_id'],
            'queue_id' => ['nullable', 'integer', 'exists:queues,id', 'required_without:assigned_to'],
        ];
    }
}

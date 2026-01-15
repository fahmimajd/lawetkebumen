<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConversationSendMessageRequest extends FormRequest
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
            'type' => ['required', 'in:text,image,video,audio,document,sticker'],
            'text' => ['nullable', 'string', 'required_if:type,text'],
            'file' => ['nullable', 'file', 'required_unless:type,text'],
            'reply_to_message_id' => ['nullable', 'integer', 'exists:messages,id'],
        ];
    }
}

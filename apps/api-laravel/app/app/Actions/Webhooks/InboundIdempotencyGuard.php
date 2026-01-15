<?php

namespace App\Actions\Webhooks;

use App\Models\Message;

class InboundIdempotencyGuard
{
    /**
     * @param array<string, mixed> $data
     * @return array{duplicate: bool, fingerprint: string}
     */
    public function guard(array $data): array
    {
        $waMessageId = isset($data['wa_message_id']) ? (string) $data['wa_message_id'] : '';
        $fingerprint = $this->fingerprint($data);

        if ($waMessageId !== '' && Message::where('wa_message_id', $waMessageId)->exists()) {
            return [
                'duplicate' => true,
                'fingerprint' => $fingerprint,
            ];
        }

        if ($fingerprint !== '' && Message::where('inbound_fingerprint', $fingerprint)->exists()) {
            return [
                'duplicate' => true,
                'fingerprint' => $fingerprint,
            ];
        }

        return [
            'duplicate' => false,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fingerprint(array $data): string
    {
        $media = is_array($data['media'] ?? null) ? $data['media'] : [];

        $source = [
            'from_wa_id' => (string) ($data['from_wa_id'] ?? ''),
            'group_wa_id' => (string) ($data['group_wa_id'] ?? ''),
            'sender_wa_id' => (string) ($data['sender_wa_id'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'wa_timestamp' => (string) ($data['wa_timestamp'] ?? ''),
            'type' => (string) ($data['type'] ?? ''),
            'text' => (string) ($data['text'] ?? ''),
            'caption' => (string) ($data['caption'] ?? ''),
            'media_mime' => (string) ($media['mime'] ?? ''),
            'media_size' => (string) ($media['size'] ?? ''),
            'media_url' => (string) ($media['url'] ?? ''),
        ];

        $encoded = json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $encoded ?: '');
    }
}

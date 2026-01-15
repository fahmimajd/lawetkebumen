<?php

namespace App\Actions\Messages;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\BroadcasterAfterCommit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeleteConversationMessage
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation, Message $message): array
    {
        if (! $user->can('view', $conversation)) {
            throw new AuthorizationException('Not allowed to delete messages.');
        }

        if ($message->conversation_id !== $conversation->id) {
            throw new AuthorizationException('Message does not belong to this conversation.');
        }

        $direction = $message->direction instanceof \BackedEnum
            ? $message->direction->value
            : $message->direction;

        if ($direction === 'out') {
            $this->revokeOutgoingMessage($conversation, $message);
        }

        $deletedId = $message->id;
        $message->delete();

        $latest = $conversation->messages()
            ->with('sender')
            ->orderByDesc('wa_timestamp')
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            $conversation->last_message_at = $latest->wa_timestamp;
            $conversation->last_message_preview = $this->previewForMessage($conversation, $latest);
        } else {
            $conversation->last_message_at = null;
            $conversation->last_message_preview = null;
        }

        $conversation->save();

        BroadcasterAfterCommit::conversationUpdated($conversation);

        return [
            'status' => 'deleted',
            'message_id' => $deletedId,
            'conversation' => [
                'id' => $conversation->id,
                'last_message_at' => optional($conversation->last_message_at)->toISOString(),
                'last_message_preview' => $conversation->last_message_preview,
            ],
        ];
    }

    private function revokeOutgoingMessage(Conversation $conversation, Message $message): void
    {
        if (! $message->wa_message_id) {
            return;
        }

        $conversation->loadMissing('contact');
        $contactWaId = $conversation->contact?->wa_id;
        if (! $contactWaId) {
            throw ValidationException::withMessages([
                'message' => 'Nomor WhatsApp tujuan tidak ditemukan.',
            ]);
        }

        $url = $this->resolveRevokeUrl();
        $token = (string) config('services.wa_gateway.token');
        $timeout = (int) config('services.wa_gateway.timeout', 5);

        if (! $url || $token === '') {
            throw ValidationException::withMessages([
                'message' => 'Konfigurasi WA gateway belum lengkap.',
            ]);
        }

        try {
            Http::timeout($timeout)
                ->withToken($token)
                ->post($url, [
                    'to_wa_id' => $contactWaId,
                    'wa_message_id' => $message->wa_message_id,
                    'from_me' => true,
                ])
                ->throw();
        } catch (Throwable $exception) {
            Log::error('Failed to revoke WhatsApp message.', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'wa_message_id' => $message->wa_message_id,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'message' => 'Gagal revoke pesan di WhatsApp.',
            ]);
        }
    }

    private function resolveRevokeUrl(): ?string
    {
        $direct = (string) config('services.wa_gateway.revoke_url');
        if ($direct !== '') {
            return $direct;
        }

        $baseUrl = rtrim((string) config('services.wa_gateway.base_url'), '/');
        if ($baseUrl !== '') {
            return $baseUrl.'/revoke';
        }

        $sendUrl = (string) config('services.wa_gateway.send_url');
        if ($sendUrl === '') {
            return null;
        }

        if (str_ends_with($sendUrl, '/send')) {
            return substr($sendUrl, 0, -5).'/revoke';
        }

        return rtrim($sendUrl, '/').'/revoke';
    }

    private function previewForMessage(Conversation $conversation, Message $message): string
    {
        $type = $message->type instanceof \BackedEnum ? $message->type->value : $message->type;
        $previewSource = $message->body ?: ($type ? '['.$type.']' : '');
        $contactWaId = $conversation->contact?->wa_id;
        $isGroup = is_string($contactWaId) && str_contains($contactWaId, '@g.us');

        $direction = $message->direction instanceof \BackedEnum
            ? $message->direction->value
            : $message->direction;

        if ($isGroup && $direction === 'in') {
            $senderName = $message->sender_name ?: $message->sender?->name;
            if ($senderName) {
                $previewSource = $senderName.': '.$previewSource;
            }
        }

        return Str::limit($previewSource, 252);
    }
}

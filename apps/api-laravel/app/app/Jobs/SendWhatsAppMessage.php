<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Support\BroadcasterAfterCommit;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $messageId)
    {
    }

    public function handle(): void
    {
        $message = Message::with('conversation.contact')->find($this->messageId);

        if (! $message) {
            return;
        }

        $status = $message->status instanceof \BackedEnum ? $message->status->value : $message->status;

        if (in_array($status, ['sent', 'delivered', 'read'], true)) {
            return;
        }

        if ($message->direction instanceof \BackedEnum) {
            $direction = $message->direction->value;
        } else {
            $direction = $message->direction;
        }

        if ($direction !== 'out') {
            return;
        }

        $conversation = $message->conversation;
        $contact = $conversation?->contact;
        $correlationId = $message->client_message_id ?: (string) $message->id;

        Log::withContext([
            'message_id' => $message->id,
            'conversation_id' => $conversation?->id,
            'client_message_id' => $message->client_message_id,
            'wa_message_id' => $message->wa_message_id,
            'correlation_id' => $correlationId,
        ]);

        if (! $contact || ! $contact->wa_id) {
            $this->markFailed($message, 'missing_contact', 'Contact not found for outbound message.');
            return;
        }

        $type = $message->type instanceof \BackedEnum ? $message->type->value : $message->type;
        $hasMedia = (bool) ($message->storage_path || $message->media_url);
        $replyPayload = $this->buildReplyPayload($message, $conversation);

        if ($type === 'text' && ! $message->body && ! $hasMedia) {
            $this->markFailed($message, 'missing_body', 'Outbound message body is empty.');
            return;
        }

        if ($type === 'text' && $hasMedia) {
            $type = $this->inferMediaType($message);
        }

        $url = config('services.wa_gateway.send_url');
        $token = config('services.wa_gateway.token');
        $timeout = (int) config('services.wa_gateway.timeout', 5);

        if (! $url || ! $token) {
            $this->markFailed($message, 'missing_config', 'WA gateway config is missing.');
            return;
        }

        try {
            $payload = [
                'client_message_id' => $message->client_message_id,
                'to_wa_id' => $contact->wa_id,
                'type' => $type,
                'text' => $message->body,
            ];

            if ($type !== 'text') {
                $mediaUrl = $this->resolveGatewayMediaUrl($message);

                if (! $mediaUrl) {
                    $this->markFailed($message, 'missing_media', 'Outbound media file not found.');
                    return;
                }

                $payload['media_url'] = $mediaUrl;
                $payload['media_mime'] = $message->media_mime;
                $payload['media_name'] = $message->storage_path
                    ? basename($message->storage_path)
                    : basename(parse_url($mediaUrl, PHP_URL_PATH) ?? '');
            }

            if ($replyPayload) {
                $payload = array_merge($payload, $replyPayload);
            }

            $response = Http::timeout($timeout)
                ->withHeaders(['X-Correlation-Id' => $correlationId])
                ->withToken($token)
                ->post($url, $payload)
                ->throw();

            $data = $response->json();
            $waMessageId = $data['wa_message_id'] ?? null;
            $waTimestamp = $data['wa_timestamp'] ?? null;

            $message->wa_message_id = $waMessageId;
            $message->wa_timestamp = $waTimestamp ? Carbon::parse($waTimestamp) : now();
            $message->status = MessageStatus::Sent;
            $message->error_code = null;
            $message->error_message = null;
            $message->save();
            BroadcasterAfterCommit::messageStatusUpdated($message);
        } catch (Throwable $exception) {
            $code = $exception instanceof RequestException
                ? (string) optional($exception->response)->status()
                : (string) $exception->getCode();

            Log::error('Outbound message send failed.', [
                'error_code' => $code !== '' ? $code : 'send_failed',
                'error_message' => $exception->getMessage(),
            ]);

            $this->markFailed($message, $code !== '' ? $code : 'send_failed', $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 2, 4, 8, 16];
    }

    private function markFailed(Message $message, string $code, string $messageText): void
    {
        $message->status = MessageStatus::Failed;
        $message->error_code = $code;
        $message->error_message = $messageText;
        $message->save();
        BroadcasterAfterCommit::messageStatusUpdated($message);
    }

    private function resolveGatewayMediaUrl(Message $message): ?string
    {
        if ($message->storage_path) {
            $baseUrl = rtrim((string) config('services.wa_gateway.media_base_url'), '/');
            if ($baseUrl !== '') {
                return $baseUrl.'/storage/'.$message->storage_path;
            }
        }

        if ($message->media_url) {
            return $message->media_url;
        }

        if ($message->storage_path) {
            return Storage::disk('public')->url($message->storage_path);
        }

        return null;
    }

    private function inferMediaType(Message $message): string
    {
        $mime = strtolower((string) ($message->media_mime ?? ''));

        if ($mime !== '') {
            if (str_starts_with($mime, 'image/')) {
                return 'image';
            }
            if (str_starts_with($mime, 'video/')) {
                return 'video';
            }
            if (str_starts_with($mime, 'audio/')) {
                return 'audio';
            }
        }

        return 'document';
    }

    /**
     * @return array<string, string>|null
     */
    private function buildReplyPayload(Message $message, ?\App\Models\Conversation $conversation): ?array
    {
        $replyToId = $message->reply_to_message_id;
        if (! $replyToId) {
            return null;
        }

        $replyTo = Message::where('id', $replyToId)->first();

        if (! $replyTo || $replyTo->conversation_id !== $message->conversation_id) {
            return null;
        }

        $replyDirection = $replyTo->direction instanceof \BackedEnum
            ? $replyTo->direction->value
            : $replyTo->direction;

        if ($replyDirection !== 'in' || ! $replyTo->wa_message_id) {
            return null;
        }

        $replyType = $replyTo->type instanceof \BackedEnum ? $replyTo->type->value : $replyTo->type;
        $replyText = $replyTo->body ?: '['.$replyType.']';
        $senderWaId = $replyTo->sender_wa_id ?: $conversation?->contact?->wa_id;
        $payload = [
            'reply_to_wa_message_id' => $replyTo->wa_message_id,
            'reply_to_text' => $replyText,
            'reply_to_type' => $replyType,
        ];

        if ($senderWaId) {
            $payload['reply_to_sender_wa_id'] = $senderWaId;
        }

        return $payload;
    }
}

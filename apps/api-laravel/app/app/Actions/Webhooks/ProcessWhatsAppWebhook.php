<?php

namespace App\Actions\Webhooks;

use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessWhatsAppWebhook
{
    public function __construct(
        private WebhookSignatureVerifier $verifier,
        private ProcessIncomingWebhook $incomingWebhook,
        private AckUpdater $ackUpdater,
        private ProcessOutgoingWebhook $outgoingWebhook
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Request $request): array
    {
        $verified = $this->verifier->verify($request);
        $payload = $verified['payload'];
        $eventId = $verified['event_id'];
        $eventType = $verified['event_type'];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $waMessageId = is_string($data['wa_message_id'] ?? null) ? $data['wa_message_id'] : null;
        $correlationId = (string) ($payload['correlation_id'] ?? $eventId);

        Log::withContext([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'correlation_id' => $correlationId,
            'wa_message_id' => $waMessageId,
        ]);

        $webhookEvent = $this->storeWebhookEvent($payload, $eventId, $eventType);

        if (! $webhookEvent) {
            return ['status' => 'duplicate'];
        }

        try {
            if ($eventType === 'message.incoming') {
                $result = $this->incomingWebhook->handle($data);
            } elseif ($eventType === 'message.outgoing') {
                $result = $this->outgoingWebhook->handle($data);
            } elseif ($eventType === 'message.ack') {
                $result = $this->ackUpdater->handle($data);
                $result = ['status' => $result];
            } else {
                $result = ['status' => 'ignored'];
            }
        } catch (Throwable $exception) {
            $webhookEvent->update([
                'status' => 'failed',
                'processed_at' => now(),
                'error' => Str::limit($exception->getMessage(), 2000),
            ]);

            throw $exception;
        }

        $status = $result['status'] ?? 'processed';
        $webhookEvent->update([
            'status' => $status === 'processed' ? 'processed' : 'ignored',
            'processed_at' => now(),
        ]);

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function storeWebhookEvent(array $payload, string $eventId, string $eventType): ?WebhookEvent
    {
        $now = now();
        $inserted = WebhookEvent::query()->insertOrIgnore([
            'source' => 'wa-gateway',
            'event_type' => $eventType,
            'event_id' => $eventId,
            'payload' => json_encode($payload),
            'received_at' => $now,
            'status' => 'received',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return null;
        }

        return WebhookEvent::where('event_id', $eventId)->first();
    }
}

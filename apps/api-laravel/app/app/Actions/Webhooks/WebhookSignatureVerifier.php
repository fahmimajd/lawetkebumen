<?php

namespace App\Actions\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class WebhookSignatureVerifier
{
    /**
     * @return array{payload: array<string, mixed>, event_id: string, event_type: string}
     */
    public function verify(Request $request): array
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-Signature');
        $secret = config('services.wa_gateway.webhook_secret');

        if (! $signature || ! $secret) {
            throw new UnauthorizedHttpException('webhook', 'Missing webhook signature.');
        }

        $expected = hash_hmac('sha256', $rawPayload, $secret);

        if (! hash_equals($expected, $signature)) {
            throw new UnauthorizedHttpException('webhook', 'Invalid webhook signature.');
        }

        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'payload' => 'Invalid JSON payload.',
            ]);
        }

        $eventId = (string) ($payload['event_id'] ?? '');
        $eventType = (string) ($payload['event_type'] ?? '');

        if ($eventId === '' || $eventType === '') {
            throw ValidationException::withMessages([
                'payload' => 'Missing event_id or event_type.',
            ]);
        }

        if (! Str::isUuid($eventId)) {
            throw ValidationException::withMessages([
                'payload' => 'event_id must be a UUID.',
            ]);
        }

        return [
            'payload' => $payload,
            'event_id' => $eventId,
            'event_type' => $eventType,
        ];
    }
}

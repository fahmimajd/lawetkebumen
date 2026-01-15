<?php

namespace Tests\Feature\Webhooks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_creates_message(): void
    {
        config()->set('services.wa_gateway.webhook_secret', 'test-secret');

        $payload = $this->makePayload();
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $raw, 'test-secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/wa',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $raw
        );

        $response->assertOk()->assertJson(['status' => 'processed']);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_duplicate_webhook_does_not_duplicate_message(): void
    {
        config()->set('services.wa_gateway.webhook_secret', 'test-secret');

        $payload = $this->makePayload();
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $raw, 'test-secret');

        $this->call(
            'POST',
            '/api/webhooks/wa',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $raw
        )->assertOk();

        $this->call(
            'POST',
            '/api/webhooks/wa',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $raw
        )->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_duplicate_payload_dedupes_without_wa_message_id(): void
    {
        config()->set('services.wa_gateway.webhook_secret', 'test-secret');

        $payload = $this->makePayload([
            'event_id' => (string) Str::uuid(),
            'data' => [
                'wa_message_id' => null,
                'wa_timestamp' => now()->toISOString(),
            ],
        ]);

        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $raw, 'test-secret');

        $this->call(
            'POST',
            '/api/webhooks/wa',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $raw
        )->assertOk()->assertJson(['status' => 'processed']);

        $duplicatePayload = $payload;
        $duplicatePayload['event_id'] = (string) Str::uuid();
        $rawDuplicate = json_encode($duplicatePayload, JSON_UNESCAPED_UNICODE);
        $signatureDuplicate = hash_hmac('sha256', $rawDuplicate, 'test-secret');

        $this->call(
            'POST',
            '/api/webhooks/wa',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $signatureDuplicate,
            ],
            $rawDuplicate
        )->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('webhook_events', 2);
    }

    public function test_outgoing_webhook_creates_outbound_message(): void
    {
        config()->set('services.wa_gateway.webhook_secret', 'test-secret');

        $payload = $this->makePayload([
            'event_type' => 'message.outgoing',
        ]);
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $raw, 'test-secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/wa',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $raw
        );

        $response->assertOk()->assertJson(['status' => 'processed']);
        $this->assertDatabaseHas('messages', [
            'direction' => 'out',
            'wa_message_id' => $payload['data']['wa_message_id'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function makePayload(array $overrides = []): array
    {
        $payload = [
            'event_id' => (string) Str::uuid(),
            'event_type' => 'message.incoming',
            'event_version' => 1,
            'timestamp' => now()->toISOString(),
            'data' => [
                'wa_message_id' => 'BAE5F2B1E2A1C3D4',
                'from_wa_id' => '628123456789@s.whatsapp.net',
                'phone' => '628123456789',
                'push_name' => 'Test User',
                'type' => 'text',
                'text' => 'Halo',
                'caption' => null,
                'media' => [
                    'mime' => null,
                    'size' => null,
                    'url' => null,
                ],
                'wa_timestamp' => now()->toISOString(),
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }
}

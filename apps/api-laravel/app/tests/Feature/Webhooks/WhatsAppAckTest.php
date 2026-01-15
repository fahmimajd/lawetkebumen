<?php

namespace Tests\Feature\Webhooks;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppAckTest extends TestCase
{
    use RefreshDatabase;

    public function test_ack_status_progression_is_monotonic(): void
    {
        config()->set('services.wa_gateway.webhook_secret', 'test-secret');

        $message = Message::factory()->create([
            'direction' => MessageDirection::Outbound,
            'type' => MessageType::Text,
            'status' => MessageStatus::Pending,
            'wa_message_id' => 'BAE_ACK_1',
        ]);

        $this->postAck('BAE_ACK_1', 'sent')->assertOk();
        $message->refresh();
        $this->assertSame('sent', $this->statusValue($message->status));

        $this->postAck('BAE_ACK_1', 'delivered')->assertOk();
        $message->refresh();
        $this->assertSame('delivered', $this->statusValue($message->status));

        $this->postAck('BAE_ACK_1', 'sent')->assertOk();
        $message->refresh();
        $this->assertSame('delivered', $this->statusValue($message->status));

        $this->postAck('BAE_ACK_1', 'read')->assertOk();
        $message->refresh();
        $this->assertSame('read', $this->statusValue($message->status));
    }

    public function test_out_of_order_ack_keeps_highest_status(): void
    {
        config()->set('services.wa_gateway.webhook_secret', 'test-secret');

        $message = Message::factory()->create([
            'direction' => MessageDirection::Outbound,
            'type' => MessageType::Text,
            'status' => MessageStatus::Pending,
            'wa_message_id' => 'BAE_ACK_2',
        ]);

        $this->postAck('BAE_ACK_2', 'read')->assertOk();
        $message->refresh();
        $this->assertSame('read', $this->statusValue($message->status));

        $this->postAck('BAE_ACK_2', 'delivered')->assertOk();
        $message->refresh();
        $this->assertSame('read', $this->statusValue($message->status));
    }

    private function postAck(string $waMessageId, string $ack)
    {
        $payload = [
            'event_id' => (string) Str::uuid(),
            'event_type' => 'message.ack',
            'event_version' => 1,
            'timestamp' => now()->toISOString(),
            'data' => [
                'wa_message_id' => $waMessageId,
                'ack' => $ack,
                'wa_timestamp' => now()->toISOString(),
            ],
        ];

        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $raw, 'test-secret');

        return $this->call(
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
    }

    private function statusValue(MessageStatus|string $status): string
    {
        return $status instanceof \BackedEnum ? $status->value : $status;
    }
}

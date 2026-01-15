<?php

namespace Tests\Feature\Jobs;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendWhatsAppMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_success_updates_message(): void
    {
        config()->set('services.wa_gateway.send_url', 'http://wa-gateway/send');
        config()->set('services.wa_gateway.token', 'token');
        config()->set('services.wa_gateway.timeout', 2);

        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'type' => MessageType::Text,
            'status' => MessageStatus::Pending,
            'body' => 'Hello',
            'wa_message_id' => null,
        ]);

        Http::fake([
            'http://wa-gateway/send' => Http::response([
                'wa_message_id' => 'BAE123',
                'wa_timestamp' => '2026-01-09T12:00:00Z',
            ], 200),
        ]);

        (new SendWhatsAppMessage($message->id))->handle();

        $message->refresh();

        $status = $message->status instanceof \BackedEnum ? $message->status->value : $message->status;

        $this->assertSame('sent', $status);
        $this->assertSame('BAE123', $message->wa_message_id);
        $this->assertNotNull($message->wa_timestamp);
        $this->assertNull($message->error_code);
        $this->assertNull($message->error_message);
    }

    public function test_job_failure_marks_message_failed(): void
    {
        config()->set('services.wa_gateway.send_url', 'http://wa-gateway/send');
        config()->set('services.wa_gateway.token', 'token');
        config()->set('services.wa_gateway.timeout', 2);

        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'type' => MessageType::Text,
            'status' => MessageStatus::Pending,
            'body' => 'Hello',
            'wa_message_id' => null,
        ]);

        Http::fake([
            'http://wa-gateway/send' => Http::response(['error' => 'fail'], 500),
        ]);

        try {
            (new SendWhatsAppMessage($message->id))->handle();
        } catch (\Throwable) {
            // expected
        }

        $message->refresh();

        $status = $message->status instanceof \BackedEnum ? $message->status->value : $message->status;

        $this->assertSame('failed', $status);
        $this->assertSame('500', $message->error_code);
        $this->assertNotEmpty($message->error_message);
    }
}

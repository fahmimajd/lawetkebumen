<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MessageSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_message_creates_pending_record(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'role' => Role::Admin,
        ]);

        $conversation = Conversation::factory()->create([
            'assigned_to' => $user->id,
        ]);

        $this->app->instance(ConversationLockService::class, new class extends ConversationLockService {
            public function isLockedByOther(int $conversationId, int $userId): bool
            {
                return false;
            }
        });

        $response = $this->actingAs($user)->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'type' => 'text',
                'text' => 'Hello from test',
            ]
        );

        $response->assertStatus(202)->assertJsonPath('status', 'queued');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'status' => 'pending',
            'body' => 'Hello from test',
        ]);

        Bus::assertDispatched(SendWhatsAppMessage::class);
    }

    public function test_agent_cannot_send_when_conversation_locked_by_other(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'role' => Role::Agent,
        ]);

        $conversation = Conversation::factory()->create([
            'assigned_to' => $user->id,
        ]);

        $this->app->instance(ConversationLockService::class, new class extends ConversationLockService {
            public function isLockedByOther(int $conversationId, int $userId): bool
            {
                return true;
            }
        });

        $response = $this->actingAs($user)->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'type' => 'text',
                'text' => 'Hello from test',
            ]
        );

        $response->assertStatus(403);

        $this->assertDatabaseCount('messages', 0);
        Bus::assertNotDispatched(SendWhatsAppMessage::class);
    }
}

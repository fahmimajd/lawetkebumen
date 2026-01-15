<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_conversations_returns_data(): void
    {
        $user = User::factory()->create([
            'role' => Role::Admin,
        ]);

        $conversation = Conversation::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/conversations');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id)
            ->assertJsonPath('data.0.contact.wa_id', $conversation->contact->wa_id);
    }
}

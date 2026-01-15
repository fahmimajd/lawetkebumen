<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationLockTest extends TestCase
{
    use RefreshDatabase;

    private ConversationLockService $lockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockService = new class extends ConversationLockService {
            private array $locks = [];

            public function acquire(int $conversationId, int $userId): array
            {
                if (! isset($this->locks[$conversationId])) {
                    $this->locks[$conversationId] = $userId;

                    return [
                        'status' => 'acquired',
                        'owner_id' => $userId,
                        'ttl' => 120,
                    ];
                }

                if ($this->locks[$conversationId] === $userId) {
                    return [
                        'status' => 'renewed',
                        'owner_id' => $userId,
                        'ttl' => 120,
                    ];
                }

                return [
                    'status' => 'locked',
                    'owner_id' => $this->locks[$conversationId],
                    'ttl' => 120,
                ];
            }

            public function release(int $conversationId, int $userId, bool $force = false): bool
            {
                if (! isset($this->locks[$conversationId])) {
                    return false;
                }

                if (! $force && $this->locks[$conversationId] !== $userId) {
                    return false;
                }

                unset($this->locks[$conversationId]);

                return true;
            }

            public function getOwner(int $conversationId): ?int
            {
                return $this->locks[$conversationId] ?? null;
            }

            public function isLockedByOther(int $conversationId, int $userId): bool
            {
                $owner = $this->locks[$conversationId] ?? null;

                return $owner !== null && $owner !== $userId;
            }
        };

        $this->app->instance(ConversationLockService::class, $this->lockService);
    }

    public function test_lock_acquire(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'assigned_to' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson("/api/conversations/{$conversation->id}/lock");

        $response->assertOk()->assertJson([
            'status' => 'acquired',
            'owner_id' => $user->id,
        ]);

        $this->assertSame($user->id, $this->lockService->getOwner($conversation->id));
    }

    public function test_lock_denied_when_owned_by_other(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $this->actingAs($owner)->postJson("/api/conversations/{$conversation->id}/lock")->assertOk();

        $response = $this->actingAs($other)->postJson("/api/conversations/{$conversation->id}/lock");

        $response->assertStatus(423)->assertJson([
            'status' => 'locked',
            'owner_id' => $owner->id,
        ]);
    }
}

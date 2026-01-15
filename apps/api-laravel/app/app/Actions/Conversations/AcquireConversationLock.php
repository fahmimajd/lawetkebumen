<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationLockService;
use Illuminate\Auth\Access\AuthorizationException;

class AcquireConversationLock
{
    public function __construct(private ConversationLockService $lockService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation): array
    {
        if (! $this->canAccess($user, $conversation)) {
            throw new AuthorizationException('Not allowed to lock this conversation.');
        }

        $result = $this->lockService->acquire($conversation->id, $user->id);

        $conversation->refresh();

        if ($result['status'] === 'locked' && $result['owner_id'] !== $user->id) {
            $result['http_status'] = 423;
        } else {
            $result['http_status'] = 200;
        }

        return $result;
    }

    private function canAccess(User $user, Conversation $conversation): bool
    {
        return $user->can('lock', $conversation);
    }
}

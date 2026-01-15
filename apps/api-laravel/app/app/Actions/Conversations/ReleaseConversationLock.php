<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationLockService;
use Illuminate\Auth\Access\AuthorizationException;

class ReleaseConversationLock
{
    public function __construct(private ConversationLockService $lockService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation): array
    {
        $force = $user->isAdmin();

        if (! $force && ! $user->can('lock', $conversation)) {
            throw new AuthorizationException('Not allowed to release this lock.');
        }

        $released = $this->lockService->release($conversation->id, $user->id, $force);

        if (! $released && ! $force) {
            throw new AuthorizationException('Not allowed to release this lock.');
        }

        return [
            'status' => $released ? 'released' : 'not_locked',
        ];
    }
}

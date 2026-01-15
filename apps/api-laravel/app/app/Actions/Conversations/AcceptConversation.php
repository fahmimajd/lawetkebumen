<?php

namespace App\Actions\Conversations;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationLockService;
use App\Support\BroadcasterAfterCommit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

class AcceptConversation
{
    public function __construct(private ConversationLockService $lockService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation): array
    {
        if (! $user->can('accept', $conversation)) {
            throw new AuthorizationException('Not allowed to accept this conversation.');
        }

        if ($conversation->status === ConversationStatus::Closed) {
            throw new AuthorizationException('Cannot accept a closed conversation.');
        }

        $conversation->assigned_to = $user->id;
        $conversation->assigned_at = now();
        $conversation->status = ConversationStatus::Open;
        $conversation->save();

        $this->forceLockForAssignee($conversation->id, $user->id);

        BroadcasterAfterCommit::conversationUpdated($conversation);

        return [
            'status' => 'accepted',
            'assigned_to' => $conversation->assigned_to,
            'assigned_name' => $user->name,
        ];
    }

    private function forceLockForAssignee(int $conversationId, int $userId): void
    {
        try {
            $this->lockService->release($conversationId, $userId, true);
            $this->lockService->acquire($conversationId, $userId);
        } catch (\Throwable $exception) {
            Log::warning('Failed to acquire conversation lock after accept.', [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteConversation
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation): array
    {
        if (! $user->can('delete', $conversation)) {
            throw new AuthorizationException('Not allowed to delete this conversation.');
        }

        $conversation->delete();

        return [
            'status' => 'deleted',
        ];
    }
}

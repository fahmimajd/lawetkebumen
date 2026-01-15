<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $conversation->assigned_to === $user->id;
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function read(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function assign(User $user, Conversation $conversation, ?int $assignedToId = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $assignedToId === null || $assignedToId === $user->id;
    }

    public function lock(User $user, Conversation $conversation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($conversation->assigned_to === null) {
            return true;
        }

        return $conversation->assigned_to === $user->id;
    }

    public function accept(User $user, Conversation $conversation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $conversation->assigned_to === null || $conversation->assigned_to === $user->id;
    }

    public function transfer(User $user, Conversation $conversation, ?int $assignedToId = null, ?int $queueId = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($conversation->assigned_to !== $user->id) {
            return false;
        }

        return $assignedToId === null || $assignedToId === $user->id;
    }

    public function close(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function reopen(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}

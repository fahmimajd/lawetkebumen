<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Message;

class RealtimeBroadcaster
{
    public static function conversationUpdated(Conversation $conversation): void
    {
        BroadcasterAfterCommit::conversationUpdated($conversation);
    }

    public static function messageCreated(Message $message): void
    {
        BroadcasterAfterCommit::messageCreated($message);
    }
}

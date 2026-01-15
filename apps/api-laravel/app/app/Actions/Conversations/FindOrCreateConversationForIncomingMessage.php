<?php

namespace App\Actions\Conversations;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\Queue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FindOrCreateConversationForIncomingMessage
{
    /**
     * Caller must wrap this in a transaction so the lock is effective.
     */
    public function handle(int $contactId, ?string $waMessageId, Carbon $receivedAt): Conversation
    {
        DB::table('contacts')
            ->where('id', $contactId)
            ->lockForUpdate()
            ->first();

        $openConversation = Conversation::query()
            ->where('contact_id', $contactId)
            ->whereIn('status', [ConversationStatus::Open, ConversationStatus::Pending])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($openConversation) {
            return $openConversation;
        }

        $closedConversation = Conversation::query()
            ->where('contact_id', $contactId)
            ->where('status', ConversationStatus::Closed)
            ->orderByDesc('closed_at')
            ->lockForUpdate()
            ->first();

        if ($closedConversation && $closedConversation->closed_at) {
            $threshold = $receivedAt->copy()->subHours(2);

            if ($closedConversation->closed_at->greaterThanOrEqualTo($threshold)) {
                $closedConversation->status = ConversationStatus::Open;
                $closedConversation->closed_at = null;
                $closedConversation->assigned_to = null;
                $closedConversation->assigned_at = null;
                $closedConversation->reopened_at = now();
                $closedConversation->save();

                return $closedConversation->fresh();
            }
        }

        $queueId = Queue::defaultId();

        return Conversation::create([
            'contact_id' => $contactId,
            'status' => ConversationStatus::Open,
            'assigned_to' => null,
            'assigned_at' => null,
            'closed_at' => null,
            'queue_id' => $queueId,
            'last_message_at' => null,
            'unread_count' => 0,
            'last_message_preview' => null,
        ]);
    }
}

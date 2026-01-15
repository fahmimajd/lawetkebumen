<?php

namespace App\Actions\Webhooks;

use App\Support\BroadcasterAfterCommit;
use Illuminate\Support\Facades\DB;

class ProcessOutgoingWebhook
{
    public function __construct(private OutgoingMessagePersister $outgoingPersister)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $persisted = $this->outgoingPersister->persist($data);

            if (! $persisted) {
                return ['status' => 'duplicate'];
            }

            if ($persisted['updated']) {
                BroadcasterAfterCommit::messageStatusUpdated($persisted['message'], null, false, 150);
            } else {
                BroadcasterAfterCommit::messageCreated($persisted['message'], null, false, 150);
                BroadcasterAfterCommit::conversationUpdated($persisted['conversation']);
            }

            return [
                'status' => 'processed',
                'message' => $persisted['message'],
                'conversation' => $persisted['conversation'],
            ];
        });
    }
}

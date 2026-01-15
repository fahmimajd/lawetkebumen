<?php

namespace App\Actions\Webhooks;

use App\Support\BroadcasterAfterCommit;
use Illuminate\Support\Facades\DB;

class ProcessIncomingWebhook
{
    public function __construct(
        private InboundIdempotencyGuard $idempotencyGuard,
        private IncomingMessagePersister $incomingPersister
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $guard = $this->idempotencyGuard->guard($data);

        if ($guard['duplicate']) {
            return ['status' => 'duplicate'];
        }

        return DB::transaction(function () use ($data, $guard) {
            $persisted = $this->incomingPersister->persist($data, $guard['fingerprint']);

            if (! $persisted) {
                return ['status' => 'duplicate'];
            }

            BroadcasterAfterCommit::messageCreated($persisted['message'], null, false, 150);
            BroadcasterAfterCommit::conversationUpdated($persisted['conversation']);

            return [
                'status' => 'processed',
                'message' => $persisted['message'],
                'conversation' => $persisted['conversation'],
            ];
        });
    }
}

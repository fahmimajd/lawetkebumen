<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class ConversationLockService
{
    public function __construct(private int $ttlSeconds = 120)
    {
    }

    /**
     * @return array{status:string,owner_id:int|null,ttl:int}
     */
    public function acquire(int $conversationId, int $userId): array
    {
        $key = $this->key($conversationId);
        $acquired = Redis::set($key, (string) $userId, 'EX', $this->ttlSeconds, 'NX');

        if ($acquired) {
            return [
                'status' => 'acquired',
                'owner_id' => $userId,
                'ttl' => $this->ttlSeconds,
            ];
        }

        $owner = Redis::get($key);
        $ownerId = $owner !== null ? (int) $owner : null;

        if ($ownerId === $userId) {
            Redis::expire($key, $this->ttlSeconds);

            return [
                'status' => 'renewed',
                'owner_id' => $userId,
                'ttl' => $this->ttlSeconds,
            ];
        }

        return [
            'status' => 'locked',
            'owner_id' => $ownerId,
            'ttl' => $this->ttlSeconds,
        ];
    }

    public function release(int $conversationId, int $userId, bool $force = false): bool
    {
        $key = $this->key($conversationId);
        $owner = Redis::get($key);

        if (! $owner) {
            return false;
        }

        if (! $force && (int) $owner !== $userId) {
            return false;
        }

        return (bool) Redis::del($key);
    }

    public function getOwner(int $conversationId): ?int
    {
        $owner = Redis::get($this->key($conversationId));

        return $owner !== null ? (int) $owner : null;
    }

    public function isLockedByOther(int $conversationId, int $userId): bool
    {
        $owner = $this->getOwner($conversationId);

        return $owner !== null && $owner !== $userId;
    }

    private function key(int $conversationId): string
    {
        return 'lock:conversation:'.$conversationId;
    }
}

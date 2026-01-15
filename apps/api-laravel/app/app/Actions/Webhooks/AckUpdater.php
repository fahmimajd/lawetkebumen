<?php

namespace App\Actions\Webhooks;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Support\BroadcasterAfterCommit;
use Illuminate\Support\Carbon;
use Throwable;

class AckUpdater
{
    /**
     * @param array<string, mixed> $data
     */
    public function handle(array $data): string
    {
        $waMessageId = isset($data['wa_message_id']) ? (string) $data['wa_message_id'] : '';
        $ack = isset($data['ack']) ? (string) $data['ack'] : '';

        if ($waMessageId === '' || $ack === '') {
            return 'ignored';
        }

        $message = Message::where('wa_message_id', $waMessageId)->first();

        if (! $message) {
            return 'ignored';
        }

        $nextStatus = $this->mapAckToStatus($ack);

        if (! $nextStatus) {
            return 'ignored';
        }

        $current = $message->status instanceof \BackedEnum ? $message->status->value : (string) $message->status;

        if (! $this->shouldUpgradeStatus($current, $nextStatus->value)) {
            return 'duplicate';
        }

        $message->status = $nextStatus;
        $message->wa_timestamp = $this->parseTimestamp($data['wa_timestamp'] ?? null);
        $message->save();
        BroadcasterAfterCommit::messageStatusUpdated($message, null, false, 150);

        return 'processed';
    }

    private function mapAckToStatus(string $ack): ?MessageStatus
    {
        $normalized = strtolower($ack);

        return match ($normalized) {
            'sent' => MessageStatus::Sent,
            'delivered' => MessageStatus::Delivered,
            'read' => MessageStatus::Read,
            default => null,
        };
    }

    private function shouldUpgradeStatus(string $current, string $next): bool
    {
        $order = [
            'pending' => 0,
            'sent' => 1,
            'delivered' => 2,
            'read' => 3,
        ];

        if (! isset($order[$next])) {
            return false;
        }

        $currentValue = $order[$current] ?? -1;

        return $order[$next] > $currentValue;
    }

    private function parseTimestamp(mixed $timestamp): Carbon
    {
        if (is_string($timestamp) && $timestamp !== '') {
            try {
                return Carbon::parse($timestamp);
            } catch (Throwable) {
                return now();
            }
        }

        return now();
    }
}

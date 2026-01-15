<?php

namespace App\Services\Reports;

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DailyAgentReportService
{
    /**
     * @return array<int, array<string, int|string>>
     */
    public function build(?string $date, ?int $queueId = null, ?int $teamId = null): array
    {
        $timezone = 'Asia/Jakarta';
        $targetDate = $date
            ? CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)
            : CarbonImmutable::now($timezone);
        $start = $targetDate->startOfDay()->setTimezone('UTC');
        $end = $targetDate->endOfDay()->setTimezone('UTC');

        $assignedQuery = DB::table('conversations')
            ->select('assigned_to', DB::raw('count(distinct conversations.id) as assigned_today'))
            ->whereNotNull('assigned_to')
            ->whereBetween('assigned_at', [$start, $end])
            ->when($queueId, fn ($query) => $query->where('queue_id', $queueId))
            ->groupBy('assigned_to');

        $resolvedQuery = DB::table('conversations')
            ->select('closed_by', DB::raw('count(*) as resolved_today'))
            ->whereNotNull('closed_by')
            ->whereBetween('closed_at', [$start, $end])
            ->when($queueId, fn ($query) => $query->where('queue_id', $queueId))
            ->groupBy('closed_by');

        $activeQuery = DB::table('conversations')
            ->select('assigned_to', DB::raw('count(*) as active_now'))
            ->whereNotNull('assigned_to')
            ->whereIn('status', ['open', 'pending'])
            ->when($queueId, fn ($query) => $query->where('queue_id', $queueId))
            ->groupBy('assigned_to');

        $reopenedQuery = DB::table('conversations')
            ->select('assigned_to', DB::raw('count(*) as reopened_today'))
            ->whereNotNull('assigned_to')
            ->whereBetween('reopened_at', [$start, $end])
            ->when($queueId, fn ($query) => $query->where('queue_id', $queueId))
            ->groupBy('assigned_to');

        $sentQuery = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->select('messages.user_id', DB::raw('count(*) as messages_sent_today'))
            ->whereNotNull('messages.user_id')
            ->where('messages.direction', 'out')
            ->whereBetween('messages.created_at', [$start, $end])
            ->when($queueId, fn ($query) => $query->where('conversations.queue_id', $queueId))
            ->groupBy('messages.user_id');

        $receivedQuery = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->select('conversations.assigned_to as user_id', DB::raw('count(*) as messages_received_today'))
            ->whereNotNull('conversations.assigned_to')
            ->where('messages.direction', 'in')
            ->whereBetween('messages.created_at', [$start, $end])
            ->when($queueId, fn ($query) => $query->where('conversations.queue_id', $queueId))
            ->groupBy('conversations.assigned_to');

        $usersQuery = User::query()
            ->whereIn('role', [Role::Agent->value, Role::Admin->value]);

        return $usersQuery
            ->leftJoinSub($assignedQuery, 'assigned', 'assigned.assigned_to', 'users.id')
            ->leftJoinSub($resolvedQuery, 'resolved', 'resolved.closed_by', 'users.id')
            ->leftJoinSub($activeQuery, 'active', 'active.assigned_to', 'users.id')
            ->leftJoinSub($reopenedQuery, 'reopened', 'reopened.assigned_to', 'users.id')
            ->leftJoinSub($sentQuery, 'sent', 'sent.user_id', 'users.id')
            ->leftJoinSub($receivedQuery, 'received', 'received.user_id', 'users.id')
            ->orderBy('users.name')
            ->select([
                'users.id as user_id',
                'users.name',
                DB::raw('COALESCE(assigned.assigned_today, 0) as assigned_today'),
                DB::raw('COALESCE(resolved.resolved_today, 0) as resolved_today'),
                DB::raw('COALESCE(active.active_now, 0) as active_now'),
                DB::raw('0 as transfer_out_today'),
                DB::raw('COALESCE(reopened.reopened_today, 0) as reopened_today'),
                DB::raw('COALESCE(sent.messages_sent_today, 0) as messages_sent_today'),
                DB::raw('COALESCE(received.messages_received_today, 0) as messages_received_today'),
            ])
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->user_id,
                'name' => $row->name,
                'assigned_today' => (int) $row->assigned_today,
                'resolved_today' => (int) $row->resolved_today,
                'active_now' => (int) $row->active_now,
                'transfer_out_today' => (int) $row->transfer_out_today,
                'reopened_today' => (int) $row->reopened_today,
                'messages_sent_today' => (int) $row->messages_sent_today,
                'messages_received_today' => (int) $row->messages_received_today,
            ])
            ->all();
    }
}

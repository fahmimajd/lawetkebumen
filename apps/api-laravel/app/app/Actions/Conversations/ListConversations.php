<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use BackedEnum;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ListConversations
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, $request): array
    {
        $status = $request->input('status');
        $assignedTo = $request->input('assigned_to');
        $query = $request->input('q');
        $limit = (int) ($request->input('limit') ?? 30);

        $builder = Conversation::query()
            ->with(['contact', 'assignee'])
            ->addSelect([
                'last_message_direction' => Message::query()
                    ->select('direction')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->orderByDesc('wa_timestamp')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->when($status, fn ($q) => $q->where('status', $status));

        if (! Gate::forUser($user)->allows('admin')) {
            if ($assignedTo && ! in_array($assignedTo, ['me', 'unassigned'], true) && (int) $assignedTo !== $user->id) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Agents can only filter their own conversations.',
                ]);
            }

            if ($assignedTo === 'me' || (int) $assignedTo === $user->id) {
                $builder->where('assigned_to', $user->id);
            } elseif ($assignedTo === 'unassigned') {
                $builder->whereNull('assigned_to');
            } else {
                $builder->where(function ($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                        ->orWhereNull('assigned_to');
                });
            }
        } elseif ($assignedTo) {
            $builder->where('assigned_to', $assignedTo === 'me' ? $user->id : (int) $assignedTo);
        }

        if ($query) {
            $builder->whereHas('contact', function ($contactQuery) use ($query) {
                $contactQuery->where('display_name', 'like', '%'.$query.'%')
                    ->orWhere('phone', 'like', '%'.$query.'%')
                    ->orWhere('wa_id', 'like', '%'.$query.'%');
            });
        }

        $paginator = $this->paginate($builder, $limit, $request->input('cursor'));

        return [
            'data' => collect($paginator->items())->map(function (Conversation $conversation) {
                $status = $conversation->status instanceof BackedEnum
                    ? $conversation->status->value
                    : $conversation->status;

                return [
                    'id' => $conversation->id,
                    'contact' => [
                        'wa_id' => $conversation->contact?->wa_id,
                        'display_name' => $conversation->contact?->display_name,
                        'phone' => $conversation->contact?->phone,
                    ],
                    'status' => $status,
                    'assigned_to' => $conversation->assigned_to,
                    'assigned_name' => $conversation->assignee?->name,
                    'last_message_at' => optional($conversation->last_message_at)->toISOString(),
                    'unread_count' => $conversation->unread_count,
                    'last_message_preview' => $conversation->last_message_preview,
                    'last_message_direction' => $conversation->last_message_direction,
                ];
            })->all(),
            'next_cursor' => $paginator->nextCursor() ? $paginator->nextCursor()->encode() : null,
        ];
    }

    private function paginate($builder, int $limit, ?string $cursor): CursorPaginator
    {
        return $builder
            ->orderByRaw("coalesce(last_message_at, '1970-01-01 00:00:00') desc")
            ->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }
}

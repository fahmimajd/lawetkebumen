<?php

namespace App\Actions\Messages;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ConversationLockService;
use App\Support\BroadcasterAfterCommit;
use App\Support\MessagePayload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SendConversationMessage
{
    public function __construct(private ConversationLockService $lockService)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(User $user, Conversation $conversation, array $payload): array
    {
        if (! $this->canAccess($user, $conversation)) {
            throw new AuthorizationException('Not allowed to send messages.');
        }

        if ($this->lockService->isLockedByOther($conversation->id, $user->id)) {
            throw new AuthorizationException('Conversation is locked by another agent.');
        }

        $file = $payload['file'] ?? null;
        $type = (string) $payload['type'];
        $text = isset($payload['text']) ? (string) $payload['text'] : null;
        $replyToId = isset($payload['reply_to_message_id']) ? (int) $payload['reply_to_message_id'] : null;

        $replyToMessage = null;
        if ($replyToId) {
            $replyToMessage = Message::where('id', $replyToId)
                ->where('conversation_id', $conversation->id)
                ->first();

            if (! $replyToMessage) {
                throw ValidationException::withMessages([
                    'reply_to_message_id' => 'Reply message not found in this conversation.',
                ]);
            }

            $replyDirection = $replyToMessage->direction instanceof \BackedEnum
                ? $replyToMessage->direction->value
                : $replyToMessage->direction;

            if ($replyDirection !== 'in') {
                throw ValidationException::withMessages([
                    'reply_to_message_id' => 'You can only reply to incoming messages.',
                ]);
            }

            if (! $replyToMessage->wa_message_id) {
                throw ValidationException::withMessages([
                    'reply_to_message_id' => 'Reply message is missing a WhatsApp ID.',
                ]);
            }
        }

        $mediaData = $this->storeOutboundMedia($file);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'direction' => 'out',
            'type' => $type,
            'body' => $text,
            'wa_message_id' => null,
            'client_message_id' => (string) Str::uuid(),
            'wa_timestamp' => now(),
            'status' => 'pending',
            'error_code' => null,
            'error_message' => null,
            'media_mime' => $mediaData['mime'],
            'media_size' => $mediaData['size'],
            'media_url' => $mediaData['url'],
            'storage_path' => $mediaData['path'],
            'reply_to_message_id' => $replyToMessage?->id,
        ]);

        $conversation->last_message_at = $message->wa_timestamp;
        $conversation->last_message_preview = Str::limit($message->body ?: '', 252);
        $conversation->save();

        BroadcasterAfterCommit::messageCreated($message, $user->id, true);
        BroadcasterAfterCommit::conversationUpdated($conversation);

        dispatch(new SendWhatsAppMessage($message->id));

        return [
            'status' => 'queued',
            'message' => MessagePayload::from($message->loadMissing(['sender', 'replyTo', 'replyTo.sender'])),
        ];
    }

    private function canAccess(User $user, Conversation $conversation): bool
    {
        return $user->can('sendMessage', $conversation);
    }

    /**
     * @return array{path: string|null, url: string|null, mime: string|null, size: int|null}
     */
    private function storeOutboundMedia(mixed $file): array
    {
        if (! $file instanceof UploadedFile) {
            return ['path' => null, 'url' => null, 'mime' => null, 'size' => null];
        }

        $originalName = $this->sanitizeFilename($file->getClientOriginalName());
        $baseName = $originalName !== '' ? pathinfo($originalName, PATHINFO_FILENAME) : 'file';
        $extension = $originalName !== '' ? pathinfo($originalName, PATHINFO_EXTENSION) : '';
        if ($extension === '') {
            $extension = $file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin');
        }

        $directory = 'wa-media/'.now()->format('Y/m/d');
        $fileName = $this->uniqueFileName($directory, $baseName, $extension);
        $path = $file->storePubliclyAs($directory, $fileName, 'public');

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'mime' => $file->getClientMimeType() ?: $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }

    private function sanitizeFilename(string $name): string
    {
        $name = str_replace(['\\', '/'], '-', $name);
        $name = str_replace(["\0", "\r", "\n", "\t"], '', $name);
        return trim($name);
    }

    private function uniqueFileName(string $directory, string $baseName, string $extension): string
    {
        $safeBase = trim($baseName) !== '' ? trim($baseName) : 'file';
        $suffix = $extension !== '' ? '.'.$extension : '';
        $disk = Storage::disk('public');

        $candidate = $safeBase.$suffix;
        if (! $disk->exists($directory.'/'.$candidate)) {
            return $candidate;
        }

        for ($i = 1; $i <= 1000; $i += 1) {
            $candidate = $safeBase.'-'.$i.$suffix;
            if (! $disk->exists($directory.'/'.$candidate)) {
                return $candidate;
            }
        }

        return $safeBase.'-'.time().$suffix;
    }
}

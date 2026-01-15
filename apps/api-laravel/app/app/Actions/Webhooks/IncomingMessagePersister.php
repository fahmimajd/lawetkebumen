<?php

namespace App\Actions\Webhooks;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Actions\Conversations\FindOrCreateConversationForIncomingMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class IncomingMessagePersister
{
    public function __construct(
        private FindOrCreateConversationForIncomingMessage $conversationFinder
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    /**
     * @param array<string, mixed> $data
     * @return array{message: Message, conversation: Conversation}|null
     */
    public function persist(array $data, string $fingerprint): ?array
    {
        $waId = (string) ($data['from_wa_id'] ?? '');
        $phone = (string) ($data['phone'] ?? '');
        $pushName = $data['push_name'] ?? null;
        $groupWaId = is_string($data['group_wa_id'] ?? null) ? (string) $data['group_wa_id'] : '';
        $groupSubject = $data['group_subject'] ?? null;
        $isGroup = (bool) ($data['is_group'] ?? false) || ($groupWaId !== '' && str_contains($groupWaId, '@g.us'));
        $contactWaId = $isGroup && $groupWaId !== '' ? $groupWaId : $waId;
        $senderWaId = (string) ($data['sender_wa_id'] ?? $waId);
        $senderPhone = (string) ($data['sender_phone'] ?? $phone);
        $senderName = $data['sender_name'] ?? $pushName;

        if ($contactWaId === '') {
            return null;
        }

        $rawPhone = $phone !== '' && ! $isGroup
            ? $phone
            : Str::before($contactWaId, '@');
        $contactPhone = $this->normalizePhone($rawPhone);
        $contactNow = now();
        Contact::query()->insertOrIgnore([
            'wa_id' => $contactWaId,
            'phone' => $contactPhone,
            'display_name' => $isGroup ? $groupSubject : $pushName,
            'avatar_url' => null,
            'created_at' => $contactNow,
            'updated_at' => $contactNow,
        ]);

        $contact = Contact::where('wa_id', $contactWaId)->first();

        if (! $contact) {
            return null;
        }

        $updates = [];
        $normalizedPhone = $this->normalizePhone($phone);
        if ($normalizedPhone !== '' && $normalizedPhone !== $contact->phone && ! $isGroup) {
            $updates['phone'] = $normalizedPhone;
        }
        if ($isGroup && $groupSubject && $groupSubject !== $contact->display_name) {
            $updates['display_name'] = $groupSubject;
        }
        if (! $isGroup && $pushName && $pushName !== $contact->display_name) {
            $updates['display_name'] = $pushName;
        }
        if ($updates) {
            $contact->update($updates);
        }

        $waTimestamp = $this->parseTimestamp($data['wa_timestamp'] ?? null);
        $waMessageId = isset($data['wa_message_id']) ? (string) $data['wa_message_id'] : null;
        $conversation = $this->conversationFinder->handle($contact->id, $waMessageId, $waTimestamp);

        if (! $conversation) {
            return null;
        }

        $replyToWaMessageId = is_string($data['reply_to_wa_message_id'] ?? null)
            ? (string) $data['reply_to_wa_message_id']
            : '';
        $replyToMessage = null;
        if ($replyToWaMessageId !== '') {
            $replyToMessage = Message::where('wa_message_id', $replyToWaMessageId)->first();
            if ($replyToMessage && $replyToMessage->conversation_id !== $conversation->id) {
                $replyToMessage = null;
            }
        }

        $text = isset($data['text']) ? (string) $data['text'] : null;
        $caption = isset($data['caption']) ? (string) $data['caption'] : null;
        $type = isset($data['type']) ? (string) $data['type'] : 'text';

        $media = is_array($data['media'] ?? null) ? $data['media'] : [];
        $storedMedia = $this->storeInboundMedia($media);

        $now = now();
        $inserted = Message::query()->insertOrIgnore([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'type' => $type,
            'body' => $text ?: $caption,
            'wa_message_id' => $waMessageId,
            'client_message_id' => (string) Str::uuid(),
            'inbound_fingerprint' => $fingerprint,
            'wa_timestamp' => $waTimestamp,
            'status' => 'delivered',
            'error_code' => null,
            'error_message' => null,
            'media_mime' => $storedMedia['mime'],
            'media_size' => $storedMedia['size'],
            'media_url' => $storedMedia['url'],
            'storage_path' => $storedMedia['path'],
            'sender_wa_id' => $senderWaId !== '' ? $senderWaId : null,
            'sender_name' => is_string($senderName) ? $senderName : null,
            'sender_phone' => $senderPhone !== '' ? $senderPhone : null,
            'reply_to_message_id' => $replyToMessage?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return null;
        }

        $message = Message::where('inbound_fingerprint', $fingerprint)->first();

        if (! $message) {
            return null;
        }

        $previewSource = $text ?: $caption ?: ('['.$type.']');
        if ($isGroup && is_string($senderName) && $senderName !== '') {
            $previewSource = $senderName.': '.$previewSource;
        }
        $preview = Str::limit($previewSource, 252);

        Conversation::whereKey($conversation->id)->update([
            'last_message_at' => $waTimestamp,
            'last_message_preview' => $preview,
            'unread_count' => DB::raw('unread_count + 1'),
        ]);

        $conversation->refresh();

        return [
            'message' => $message,
            'conversation' => $conversation,
        ];
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

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits !== '') {
            return $digits;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $media
     * @return array{path: string|null, url: string|null, mime: string|null, size: int|null}
     */
    private function storeInboundMedia(array $media): array
    {
        $base64 = $media['base64'] ?? null;
        if (! is_string($base64) || $base64 === '') {
            return [
                'path' => null,
                'url' => $media['url'] ?? null,
                'mime' => $media['mime'] ?? null,
                'size' => isset($media['size']) ? (int) $media['size'] : null,
            ];
        }

        $binary = base64_decode($base64, true);
        if ($binary === false) {
            return [
                'path' => null,
                'url' => $media['url'] ?? null,
                'mime' => $media['mime'] ?? null,
                'size' => isset($media['size']) ? (int) $media['size'] : null,
            ];
        }

        $rawName = is_string($media['name'] ?? null) ? (string) $media['name'] : '';
        $originalName = $this->sanitizeFilename($rawName);
        $extension = $this->guessExtension($media['mime'] ?? null, $originalName !== '' ? $originalName : null);
        $baseName = $originalName !== '' ? pathinfo($originalName, PATHINFO_FILENAME) : 'media-'.now()->format('Ymd-His');

        $directory = 'wa-media/'.now()->format('Y/m/d');
        $fileName = $this->uniqueFileName($directory, $baseName, $extension);
        $path = $directory.'/'.$fileName;

        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'mime' => $media['mime'] ?? null,
            'size' => isset($media['size']) ? (int) $media['size'] : strlen($binary),
        ];
    }

    private function guessExtension(?string $mime, ?string $name): string
    {
        if (is_string($name) && str_contains($name, '.')) {
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($extension !== '') {
                return $extension;
            }
        }

        if (! $mime) {
            return 'bin';
        }

        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'application/pdf' => 'pdf',
        ];

        return $map[$mime] ?? (str_contains($mime, '/') ? explode('/', $mime, 2)[1] : 'bin');
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

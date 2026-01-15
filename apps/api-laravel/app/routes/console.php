<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('contacts:merge-lid {--mapping= : Path to LID mapping json} {--dry-run : Only report changes} {--keep-lid : Keep LID contacts after merge}', function () {
    $mappingPath = $this->option('mapping');
    if (! is_string($mappingPath) || $mappingPath === '') {
        $candidates = [
            storage_path('lid-mapping.json'),
            base_path('../../wa-gateway-node/storage/wa-auth/lid-mapping.json'),
            base_path('../wa-gateway-node/storage/wa-auth/lid-mapping.json'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
                $mappingPath = $candidate;
                break;
            }
        }
    }

    if (! is_string($mappingPath) || $mappingPath === '' || ! file_exists($mappingPath)) {
        $this->error("Mapping file not found: {$mappingPath}");
        return 1;
    }

    $raw = file_get_contents($mappingPath);
    if (! is_string($raw) || $raw === '') {
        $this->error("Mapping file is empty: {$mappingPath}");
        return 1;
    }

    $parsed = json_decode($raw, true);
    if (! is_array($parsed)) {
        $this->error("Mapping file is not valid JSON: {$mappingPath}");
        return 1;
    }

    $normalizeWaId = static function (string $jid): string {
        if (! str_contains($jid, '@')) {
            return $jid;
        }

        [$user, $server] = explode('@', $jid, 2);
        $baseUser = explode(':', $user, 2)[0];

        return $baseUser.'@'.$server;
    };

    $isLid = static function (string $jid) use ($normalizeWaId): bool {
        $normalized = $normalizeWaId($jid);
        return str_ends_with($normalized, '@lid') || str_ends_with($normalized, '@hosted.lid');
    };

    $extractDigits = static function (string $jid): string {
        if (! str_contains($jid, '@')) {
            return '';
        }

        $user = explode('@', $jid, 2)[0];
        $baseUser = explode(':', $user, 2)[0];
        $digits = preg_replace('/\D+/', '', $baseUser) ?? '';

        return $digits;
    };

    $dryRun = (bool) $this->option('dry-run');
    $keepLid = (bool) $this->option('keep-lid');
    $total = 0;
    $processed = 0;
    $skipped = 0;
    $contactUpdates = 0;
    $contactMerges = 0;
    $contactDeletes = 0;
    $conversationMoves = 0;
    $messageUpdates = 0;

    foreach ($parsed as $lid => $pn) {
        $total++;

        if (! is_string($lid) || ! is_string($pn)) {
            $skipped++;
            continue;
        }

        $lid = $normalizeWaId($lid);
        $pn = $normalizeWaId($pn);

        if ($lid === '' || $pn === '' || $lid === $pn || ! $isLid($lid)) {
            $skipped++;
            continue;
        }

        if (! str_contains($pn, '@')) {
            $skipped++;
            continue;
        }

        $actions = [];
        $processed++;
        $pnDigits = $extractDigits($pn);

        DB::transaction(function () use (
            $dryRun,
            $keepLid,
            $lid,
            $pn,
            $pnDigits,
            &$actions,
            &$contactUpdates,
            &$contactMerges,
            &$contactDeletes,
            &$conversationMoves,
            &$messageUpdates
        ): void {
            $lidContact = Contact::where('wa_id', $lid)->first();
            $pnContact = Contact::where('wa_id', $pn)->first();

            if ($lidContact && ! $pnContact) {
                $updates = ['wa_id' => $pn];
                if ($pnDigits !== '' && $lidContact->phone !== $pnDigits) {
                    $updates['phone'] = $pnDigits;
                }

                if (! $dryRun) {
                    $lidContact->update($updates);
                }
                $contactUpdates++;
                $actions[] = 'contact updated';
                $pnContact = $lidContact;
            } elseif ($lidContact && $pnContact) {
                $contactMerges++;
                $updates = [];
                if (($pnContact->phone === '' || $pnContact->phone !== $pnDigits) && $pnDigits !== '') {
                    $updates['phone'] = $pnDigits;
                }
                if (! $pnContact->display_name && $lidContact->display_name) {
                    $updates['display_name'] = $lidContact->display_name;
                }
                if (! $pnContact->avatar_url && $lidContact->avatar_url) {
                    $updates['avatar_url'] = $lidContact->avatar_url;
                }
                if ($updates && ! $dryRun) {
                    $pnContact->update($updates);
                }

                $conversationCount = Conversation::where('contact_id', $lidContact->id)->count();
                if ($conversationCount > 0) {
                    if (! $dryRun) {
                        Conversation::where('contact_id', $lidContact->id)->update(['contact_id' => $pnContact->id]);
                    }
                    $conversationMoves += $conversationCount;
                    $actions[] = "moved {$conversationCount} conversation(s)";
                }

                if (! $keepLid) {
                    if (! $dryRun) {
                        $lidContact->delete();
                    }
                    $contactDeletes++;
                    $actions[] = 'lid contact deleted';
                }
            }

            $messageCount = Message::where('sender_wa_id', $lid)->count();
            if ($messageCount > 0) {
                if (! $dryRun) {
                    $messageUpdate = ['sender_wa_id' => $pn];
                    if ($pnDigits !== '') {
                        $messageUpdate['sender_phone'] = $pnDigits;
                    }
                    Message::where('sender_wa_id', $lid)->update($messageUpdate);
                }
                $messageUpdates += $messageCount;
                $actions[] = "updated {$messageCount} message sender(s)";
            }
        });

        if ($actions) {
            $prefix = $dryRun ? '[dry-run]' : '[apply]';
            $this->line("{$prefix} {$lid} -> {$pn}: ".implode(', ', $actions));
        }
    }

    $this->newLine();
    $this->line('LID merge summary');
    $this->line("Total mappings: {$total}");
    $this->line("Processed: {$processed}");
    $this->line("Skipped: {$skipped}");
    $this->line("Contacts updated: {$contactUpdates}");
    $this->line("Contacts merged: {$contactMerges}");
    $this->line("Contacts deleted: {$contactDeletes}");
    $this->line("Conversations moved: {$conversationMoves}");
    $this->line("Messages updated: {$messageUpdates}");

    $remainingContacts = Contact::query()
        ->where('wa_id', 'like', '%@lid')
        ->orWhere('wa_id', 'like', '%@hosted.lid')
        ->orWhereRaw("wa_id ~ '^[0-9]{10,}$'")
        ->count();
    $remainingMessages = Message::query()
        ->where('sender_wa_id', 'like', '%@lid')
        ->orWhere('sender_wa_id', 'like', '%@hosted.lid')
        ->orWhereRaw("sender_wa_id ~ '^[0-9]{10,}$'")
        ->count();
    $this->line("Remaining LID contacts: {$remainingContacts}");
    $this->line("Remaining LID messages: {$remainingMessages}");

    return 0;
})->purpose('Merge LID contacts into PN contacts and update related records');

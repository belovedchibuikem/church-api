<?php

namespace App\Support\Communication;

use App\Communication\CommunicationChannel;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateCommunicationTemplateAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        string $code,
        CommunicationChannel $channel,
        string $locale,
        string $subject,
        string $body,
        User $actor,
    ): CommunicationTemplate {
        $normalizedCode = Str::squish($code);
        $normalizedLocale = Str::squish($locale);
        $normalizedSubject = Str::squish($subject);

        if ($normalizedCode === '' || Str::length($normalizedCode) > 100) {
            throw new InvalidArgumentException('Communication template codes must contain between 1 and 100 characters.');
        }

        if ($normalizedLocale === '' || Str::length($normalizedLocale) > 35) {
            throw new InvalidArgumentException('Communication template locales must contain between 1 and 35 characters.');
        }

        if ($normalizedSubject === '' || Str::length($normalizedSubject) > 191) {
            throw new InvalidArgumentException('Communication template subjects must contain between 1 and 191 characters.');
        }

        if (trim($body) === '') {
            throw new InvalidArgumentException('Communication template bodies cannot be empty.');
        }

        return DB::transaction(function () use (
            $normalizedCode,
            $channel,
            $normalizedLocale,
            $normalizedSubject,
            $body,
            $actor,
        ): CommunicationTemplate {
            $duplicate = CommunicationTemplate::query()
                ->where('code', $normalizedCode)
                ->where('channel', $channel)
                ->where('locale', $normalizedLocale)
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw new InvalidArgumentException('A communication template already exists for this code, channel, and locale.');
            }

            $template = CommunicationTemplate::query()->create([
                'code' => $normalizedCode,
                'channel' => $channel,
                'locale' => $normalizedLocale,
                'subject' => $normalizedSubject,
                'body' => $body,
                'created_by_user_id' => $actor->getKey(),
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'communications.template.created',
                actor: $actor,
                targetType: 'communication_template',
                targetId: $template->public_id,
                metadata: [
                    'code' => $normalizedCode,
                    'channel' => $channel->value,
                    'locale' => $normalizedLocale,
                ],
            ));

            return $template;
        }, attempts: 3);
    }
}

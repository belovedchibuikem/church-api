<?php

namespace App\Support\Communication;

use App\Communication\CommunicationChannel;
use App\Communication\CommunicationDeliveryStatus;
use App\Models\CommunicationProviderConfiguration;
use App\Models\CommunicationRecipient;
use App\Models\CommunicationTemplate;
use App\Support\Communication\Contracts\CommunicationDeliveryGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ConfiguredCommunicationDeliveryGateway implements CommunicationDeliveryGateway
{
    public function attempt(
        CommunicationRecipient $recipient,
        CommunicationTemplate $template,
        string $idempotencyKey,
    ): CommunicationDeliveryResult {
        $configuration = CommunicationProviderConfiguration::query()->where('is_active', true)->first();
        $channel = $template->channel?->value ?? $recipient->broadcast?->channel?->value;

        if ($configuration === null || ! is_string($channel) || ! $configuration->channelConfigured($channel)) {
            return CommunicationDeliveryResult::providerUnconfigured();
        }

        $recipient->loadMissing(['user', 'person.user']);

        return match ($channel) {
            CommunicationChannel::Email->value => $this->sendEmail($configuration, $recipient, $template),
            CommunicationChannel::Sms->value => $this->sendHttp(
                'https://api.ng.termii.com/api/sms/send',
                [
                    'api_key' => $configuration->sms_api_key,
                    'to' => $this->destination($recipient),
                    'from' => $configuration->sms_sender_id ?: 'FHC',
                    'sms' => $template->body,
                    'type' => 'plain',
                    'channel' => 'generic',
                ],
            ),
            CommunicationChannel::WhatsApp->value => $this->sendHttp(
                'https://graph.facebook.com/v20.0/'.rawurlencode((string) $configuration->whatsapp_phone_number_id).'/messages',
                [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->destination($recipient),
                    'type' => 'text',
                    'text' => ['body' => $template->body],
                ],
                (string) $configuration->whatsapp_access_token,
            ),
            CommunicationChannel::Push->value => $this->sendHttp(
                'https://fcm.googleapis.com/fcm/send',
                [
                    'to' => $this->destination($recipient),
                    'notification' => [
                        'title' => $template->subject ?: 'Family House Connect',
                        'body' => $template->body,
                    ],
                ],
                (string) $configuration->push_server_key,
            ),
            default => CommunicationDeliveryResult::providerUnconfigured(),
        };
    }

    private function sendEmail(
        CommunicationProviderConfiguration $configuration,
        CommunicationRecipient $recipient,
        CommunicationTemplate $template,
    ): CommunicationDeliveryResult {
        $address = $recipient->user?->email ?? $recipient->person?->user?->email;
        if (! is_string($address) || $address === '') {
            return new CommunicationDeliveryResult(CommunicationDeliveryStatus::Failed, 'destination_missing');
        }

        if ($configuration->email_provider === 'smtp') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => (string) $configuration->email_smtp_host,
                'mail.mailers.smtp.port' => (int) $configuration->email_smtp_port,
                'mail.mailers.smtp.username' => (string) $configuration->email_smtp_username,
                'mail.mailers.smtp.password' => (string) $configuration->email_api_key,
                'mail.mailers.smtp.encryption' => $configuration->email_smtp_encryption === 'none'
                    ? null
                    : ($configuration->email_smtp_encryption ?: 'tls'),
            ]);
        }

        Mail::raw((string) $template->body, function ($message) use ($configuration, $address, $template): void {
            $message->to($address)
                ->from((string) $configuration->email_sender_address, (string) ($configuration->email_sender_name ?: 'Family House Connect'))
                ->subject((string) ($template->subject ?: 'Family House Connect'));
        });

        return new CommunicationDeliveryResult(CommunicationDeliveryStatus::Succeeded, 'accepted');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendHttp(string $url, array $payload, ?string $bearer = null): CommunicationDeliveryResult
    {
        $request = Http::acceptJson()->timeout(15);
        if ($bearer) {
            $request = $request->withToken($bearer);
        }

        $response = $request->post($url, $payload);

        return $response->successful()
            ? new CommunicationDeliveryResult(CommunicationDeliveryStatus::Succeeded, 'accepted')
            : new CommunicationDeliveryResult(CommunicationDeliveryStatus::Failed, 'provider_rejected');
    }

    private function destination(CommunicationRecipient $recipient): string
    {
        $recipient->loadMissing(['user', 'person.user']);

        return (string) ($recipient->user?->email ?? $recipient->person?->user?->email ?? $recipient->public_id);
    }
}

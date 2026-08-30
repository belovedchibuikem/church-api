<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationProviderConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'configured' => true,
            'active' => $this->is_active,
            'email' => [
                'provider' => $this->email_provider,
                'sender_name' => $this->email_sender_name,
                'sender_address' => $this->email_sender_address,
                'credentials_configured' => $this->channelConfigured('email'),
                'smtp' => [
                    'host' => $this->email_smtp_host,
                    'port' => $this->email_smtp_port,
                    'username' => $this->email_smtp_username,
                    'encryption' => $this->email_smtp_encryption ?? 'tls',
                    'password_configured' => filled($this->email_api_key),
                ],
            ],
            'sms' => [
                'provider' => $this->sms_provider,
                'sender_id' => $this->sms_sender_id,
                'credentials_configured' => $this->channelConfigured('sms'),
            ],
            'whatsapp' => [
                'provider' => $this->whatsapp_provider,
                'phone_number_id' => $this->whatsapp_phone_number_id,
                'credentials_configured' => $this->channelConfigured('whatsapp'),
            ],
            'push' => [
                'provider' => $this->push_provider,
                'credentials_configured' => $this->channelConfigured('push'),
            ],
            'consent_required_channels' => $this->consent_required_channels,
            'retry' => [
                'max_attempts' => $this->retry_max_attempts,
                'backoff_seconds' => $this->retry_backoff_seconds,
            ],
            'configuration_revision' => $this->configuration_revision,
            'activated_at' => $this->activated_at?->utc()->toIso8601String(),
        ];
    }
}

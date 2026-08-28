<?php

namespace App\Models;

use Database\Factories\CommunicationProviderConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'email_provider',
    'email_sender_name',
    'email_sender_address',
    'email_api_key',
    'sms_provider',
    'sms_sender_id',
    'sms_api_key',
    'sms_api_secret',
    'whatsapp_provider',
    'whatsapp_phone_number_id',
    'whatsapp_access_token',
    'push_provider',
    'push_server_key',
    'consent_required_channels',
    'retry_max_attempts',
    'retry_backoff_seconds',
])]
#[Hidden([
    'email_api_key',
    'sms_api_key',
    'sms_api_secret',
    'whatsapp_access_token',
    'push_server_key',
])]
class CommunicationProviderConfiguration extends Model
{
    /** @use HasFactory<CommunicationProviderConfigurationFactory> */
    use HasFactory;

    protected $attributes = [
        'email_provider' => 'none',
        'sms_provider' => 'none',
        'whatsapp_provider' => 'none',
        'push_provider' => 'none',
        'consent_required_channels' => '["email","sms","whatsapp","push"]',
        'retry_max_attempts' => 3,
        'retry_backoff_seconds' => 60,
        'is_active' => false,
        'configuration_revision' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_api_key' => 'encrypted',
            'sms_api_key' => 'encrypted',
            'sms_api_secret' => 'encrypted',
            'whatsapp_access_token' => 'encrypted',
            'push_server_key' => 'encrypted',
            'consent_required_channels' => 'array',
            'retry_max_attempts' => 'integer',
            'retry_backoff_seconds' => 'integer',
            'is_active' => 'boolean',
            'configuration_revision' => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    public function channelConfigured(string $channel): bool
    {
        return match ($channel) {
            'email' => $this->email_provider !== 'none' && filled($this->email_sender_address) && filled($this->email_api_key),
            'sms' => $this->sms_provider !== 'none' && filled($this->sms_api_key),
            'whatsapp' => $this->whatsapp_provider !== 'none' && filled($this->whatsapp_access_token),
            'push' => $this->push_provider !== 'none' && filled($this->push_server_key),
            'in_app' => true,
            default => false,
        };
    }
}

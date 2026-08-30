<?php

namespace App\Support\Communication;

use App\Models\CommunicationProviderConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class ConfigureCommunicationProviderAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?User $actor = null): CommunicationProviderConfiguration
    {
        return DB::transaction(function () use ($input, $actor): CommunicationProviderConfiguration {
            $configuration = CommunicationProviderConfiguration::query()->lockForUpdate()->first()
                ?? new CommunicationProviderConfiguration;

            foreach ([
                'email_provider',
                'email_sender_name',
                'email_sender_address',
                'email_smtp_host',
                'email_smtp_username',
                'email_smtp_encryption',
                'sms_provider',
                'sms_sender_id',
                'whatsapp_provider',
                'whatsapp_phone_number_id',
                'push_provider',
            ] as $field) {
                if (array_key_exists($field, $input) && $input[$field] !== null) {
                    $configuration->{$field} = $input[$field];
                }
            }

            if (array_key_exists('email_smtp_port', $input) && $input['email_smtp_port'] !== null) {
                $configuration->email_smtp_port = (int) $input['email_smtp_port'];
            }

            foreach (['email_api_key', 'email_smtp_password', 'sms_api_key', 'sms_api_secret', 'whatsapp_access_token', 'push_server_key'] as $secret) {
                if (array_key_exists($secret, $input) && filled($input[$secret])) {
                    $target = $secret === 'email_smtp_password' ? 'email_api_key' : $secret;
                    $configuration->{$target} = (string) $input[$secret];
                }
            }

            if (array_key_exists('consent_required_channels', $input)) {
                $configuration->consent_required_channels = array_values($input['consent_required_channels']);
            }

            if (isset($input['retry_max_attempts'])) {
                $configuration->retry_max_attempts = (int) $input['retry_max_attempts'];
            }

            if (isset($input['retry_backoff_seconds'])) {
                $configuration->retry_backoff_seconds = (int) $input['retry_backoff_seconds'];
            }

            $configuration->forceFill([
                'is_active' => false,
                'configuration_revision' => $configuration->exists ? $configuration->configuration_revision + 1 : 1,
                'activated_at' => null,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'platform.communications.configured',
                actor: $actor,
                targetType: 'communication_provider_configuration',
                targetId: (string) $configuration->getKey(),
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'email_provider' => $configuration->email_provider,
                    'sms_provider' => $configuration->sms_provider,
                    'whatsapp_provider' => $configuration->whatsapp_provider,
                    'push_provider' => $configuration->push_provider,
                    'configuration_revision' => $configuration->configuration_revision,
                ],
            ));

            return $configuration->refresh();
        });
    }
}

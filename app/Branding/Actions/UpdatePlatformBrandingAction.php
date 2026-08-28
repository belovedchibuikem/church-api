<?php

namespace App\Branding\Actions;

use App\Branding\PlatformBrandingPresenter;
use App\Models\PlatformBrandingConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class UpdatePlatformBrandingAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(string $appName, ?User $actor = null): PlatformBrandingConfiguration
    {
        $name = trim($appName);
        if ($name === '') {
            $name = PlatformBrandingPresenter::defaultAppName();
        }

        return DB::transaction(function () use ($name, $actor): PlatformBrandingConfiguration {
            $configuration = PlatformBrandingConfiguration::query()->lockForUpdate()->first()
                ?? new PlatformBrandingConfiguration;

            $configuration->forceFill([
                'app_name' => $name,
                'configuration_revision' => $configuration->exists
                    ? $configuration->configuration_revision + 1
                    : 1,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'platform.branding.updated',
                actor: $actor,
                targetType: 'platform_branding_configuration',
                targetId: (string) $configuration->getKey(),
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'app_name' => $configuration->app_name,
                    'configuration_revision' => $configuration->configuration_revision,
                ],
            ));

            return $configuration->refresh()->load(['logoFile', 'faviconFile']);
        });
    }
}

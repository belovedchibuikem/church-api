<?php

namespace App\Branding\Actions;

use App\Branding\PlatformBrandAssetKind;
use App\Models\PlatformBrandingConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RemovePlatformBrandAssetAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(PlatformBrandAssetKind $kind, ?User $actor = null): PlatformBrandingConfiguration
    {
        return DB::transaction(function () use ($kind, $actor): PlatformBrandingConfiguration {
            $configuration = PlatformBrandingConfiguration::query()->lockForUpdate()->first();
            if ($configuration === null) {
                throw new NotFoundHttpException;
            }

            $configuration->forceFill([
                $kind->column() => null,
                'configuration_revision' => $configuration->configuration_revision + 1,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'platform.branding.asset_removed',
                actor: $actor,
                targetType: 'platform_branding_configuration',
                targetId: (string) $configuration->getKey(),
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'kind' => $kind->value,
                    'configuration_revision' => $configuration->configuration_revision,
                ],
            ));

            return $configuration->refresh()->load(['logoFile', 'faviconFile']);
        });
    }
}

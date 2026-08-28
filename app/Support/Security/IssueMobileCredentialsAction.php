<?php

namespace App\Support\Security;

use App\Models\Device;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class IssueMobileCredentialsAction
{
    public function __construct(
        private MobileCredentialIssuer $issuer,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(User $user, Device $device, SecuritySession $securitySession): IssuedMobileCredentials
    {
        return DB::transaction(function () use ($user, $device, $securitySession): IssuedMobileCredentials {
            $credentials = $this->issuer->issue($user, $device, $securitySession);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.mobile_credentials.issued',
                actor: $user,
                targetType: 'security_session',
                targetId: $securitySession->public_id,
                metadata: [
                    'device_public_id' => $device->public_id,
                    'credential_family_id' => $credentials->accessToken->family_id,
                ],
            ));

            return $credentials;
        }, attempts: 3);
    }
}

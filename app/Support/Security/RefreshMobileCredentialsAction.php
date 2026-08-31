<?php

namespace App\Support\Security;

use App\Models\Device;
use App\Models\MobileRefreshToken;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;

class RefreshMobileCredentialsAction
{
    public function __construct(
        private MobileCredentialHasher $hasher,
        private MobileCredentialIssuer $issuer,
        private RevokeMobileCredentialFamilyAction $revokeFamily,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(string $plainRefreshToken, string $deviceIdentifier): IssuedMobileCredentials
    {
        $result = DB::transaction(function () use ($plainRefreshToken, $deviceIdentifier): array {
            $refreshToken = MobileRefreshToken::query()
                ->where('token_hash', $this->hasher->hash($plainRefreshToken))
                ->lockForUpdate()
                ->first();

            if ($refreshToken === null) {
                return ['status' => 'invalid'];
            }

            $securitySession = SecuritySession::query()->lockForUpdate()->find($refreshToken->security_session_id);
            $device = Device::query()->lockForUpdate()->find($refreshToken->device_id);
            $user = User::query()->lockForUpdate()->find($refreshToken->user_id);

            if ($securitySession === null || $device === null || $user === null) {
                return ['status' => 'invalid'];
            }

            if ($refreshToken->used_at !== null) {
                $this->revokeFamily->handle(
                    $securitySession,
                    $refreshToken->family_id,
                    'refresh_reuse_detected',
                    $user,
                );

                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'security.mobile_refresh.reuse_detected',
                    actor: $user,
                    targetType: 'security_session',
                    targetId: $securitySession->public_id,
                    metadata: [
                        'device_public_id' => $device->public_id,
                        'credential_family_id' => $refreshToken->family_id,
                    ],
                ));

                return ['status' => 'reuse'];
            }

            if (
                $refreshToken->revoked_at !== null
                || $refreshToken->expires_at->isPast()
                || $securitySession->revoked_at !== null
                || $securitySession->expires_at?->isPast()
                || $device->revoked_at !== null
                || $user->isSuspended()
                || ! hash_equals($device->identifier_hash, $this->hasher->hash($deviceIdentifier))
            ) {
                return ['status' => 'invalid'];
            }

            $refreshToken->forceFill(['used_at' => now()->utc()])->save();
            $credentials = $this->issuer->issue($user, $device, $securitySession, $refreshToken->family_id);
            $refreshToken->forceFill(['replaced_by_id' => $credentials->refreshToken->getKey()])->save();

            $seenAt = now()->utc();
            $securitySession->forceFill(['last_seen_at' => $seenAt])->save();
            $device->forceFill(['last_seen_at' => $seenAt])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.mobile_credentials.rotated',
                actor: $user,
                targetType: 'security_session',
                targetId: $securitySession->public_id,
                metadata: [
                    'device_public_id' => $device->public_id,
                    'credential_family_id' => $refreshToken->family_id,
                ],
            ));

            return ['status' => 'issued', 'credentials' => $credentials];
        }, attempts: 3);

        if ($result['status'] !== 'issued' || ! $result['credentials'] instanceof IssuedMobileCredentials) {
            throw new AuthenticationException;
        }

        return $result['credentials'];
    }
}

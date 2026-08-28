<?php

namespace App\Support\Security;

use App\Models\Device;
use App\Models\MobileAccessToken;
use App\Models\MobileRefreshToken;
use App\Models\SecuritySession;
use App\Models\User;

final readonly class IssuedMobileCredentials
{
    public function __construct(
        public User $user,
        public Device $device,
        public SecuritySession $securitySession,
        public MobileAccessToken $accessToken,
        public MobileRefreshToken $refreshToken,
        public string $plainAccessToken,
        public string $plainRefreshToken,
    ) {}
}

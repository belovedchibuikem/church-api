<?php

namespace App\Support\Security;

use Illuminate\Http\Request;

final class ClientNetworkContext
{
    /**
     * @return array{ip: ?string, country: ?string}
     */
    public static function fromRequest(?Request $request = null): array
    {
        $request ??= request();
        if (! $request instanceof Request) {
            return ['ip' => null, 'country' => null];
        }

        $header = $request->headers->get('CF-IPCountry')
            ?? $request->headers->get('X-Country-Code');
        $country = is_string($header) && preg_match('/\A[A-Za-z]{2}\z/', $header) === 1
            ? strtoupper($header)
            : null;

        $ip = $request->ip();

        return [
            'ip' => is_string($ip) && $ip !== '' ? $ip : null,
            'country' => $country,
        ];
    }
}

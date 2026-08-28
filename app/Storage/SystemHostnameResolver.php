<?php

namespace App\Storage;

use App\Storage\Contracts\HostnameResolver;

class SystemHostnameResolver implements HostnameResolver
{
    public function resolve(string $hostname): array
    {
        if (filter_var($hostname, FILTER_VALIDATE_IP) !== false) {
            return [$hostname];
        }

        $addresses = gethostbynamel($hostname) ?: [];
        $records = @dns_get_record($hostname, DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}

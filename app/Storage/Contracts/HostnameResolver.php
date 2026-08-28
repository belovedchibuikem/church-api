<?php

namespace App\Storage\Contracts;

interface HostnameResolver
{
    /** @return list<string> */
    public function resolve(string $hostname): array;
}

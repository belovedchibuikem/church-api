<?php

namespace App\Storage\Data;

final readonly class S3ConnectionData
{
    public function __construct(
        public string $accessKeyId,
        public string $secretAccessKey,
        public string $region,
        public string $bucket,
        public ?string $endpoint = null,
        public ?string $url = null,
        public ?string $rootPrefix = null,
        public bool $usePathStyleEndpoint = false,
    ) {}
}

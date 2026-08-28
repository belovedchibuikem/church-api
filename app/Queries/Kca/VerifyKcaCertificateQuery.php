<?php

namespace App\Queries\Kca;

use App\Models\KcaCertificate;
use App\Support\Kca\KcaCertificateCodeHasher;

class VerifyKcaCertificateQuery
{
    public function __construct(private KcaCertificateCodeHasher $codeHasher) {}

    public function handle(string $verificationCode): KcaCertificate
    {
        return KcaCertificate::query()
            ->with('revocation')
            ->select(['id', 'public_id', 'certificate_number', 'completion_on', 'issued_at'])
            ->where('verification_code_hash', $this->codeHasher->hash($verificationCode))
            ->firstOrFail();
    }
}

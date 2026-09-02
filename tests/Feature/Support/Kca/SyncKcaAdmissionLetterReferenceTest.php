<?php

namespace Tests\Feature\Support\Kca;

use App\Support\Kca\SyncKcaAdmissionLetterReference;
use PHPUnit\Framework\TestCase;

class SyncKcaAdmissionLetterReferenceTest extends TestCase
{
    public function test_it_replaces_stale_reference_line_with_canonical_code(): void
    {
        $body = "Ref. No.: KCA/ADM/2026/2026/00001\nDate: 02/09/2026";

        $updated = SyncKcaAdmissionLetterReference::inBody($body, 'KCA/ADM/2026/00001');

        $this->assertSame("Ref. No.: KCA/ADM/2026/00001\nDate: 02/09/2026", $updated);
    }
}

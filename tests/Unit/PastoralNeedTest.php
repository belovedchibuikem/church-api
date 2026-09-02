<?php

namespace Tests\Unit;

use App\Models\PastoralNeed;
use PHPUnit\Framework\TestCase;

class PastoralNeedTest extends TestCase
{
    public function test_display_title_prefixes_home_church_scope(): void
    {
        $need = new PastoralNeed([
            'summary' => 'Bibles for new members',
            'home_church_id' => 1,
        ]);
        $need->setRelation('homeChurch', (object) ['name' => 'Grace Home Church']);

        $this->assertStringStartsWith('Home Church Need — Grace Home Church —', $need->displayTitle());
        $this->assertSame('home_church', $need->scopeType());
    }

    public function test_display_title_prefixes_church_scope(): void
    {
        $need = new PastoralNeed([
            'summary' => 'Sanctuary projector replacement',
            'church_id' => 1,
        ]);
        $need->setRelation('church', (object) ['name' => 'The Covenant Place']);

        $this->assertStringStartsWith('Church Need — The Covenant Place —', $need->displayTitle());
        $this->assertSame('church', $need->scopeType());
    }
}

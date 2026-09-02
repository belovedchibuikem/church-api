<?php

namespace Tests\Feature\Support\Kca;

use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaYear;
use App\Support\Kca\GenerateKcaRegistrationNumberAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GenerateKcaRegistrationNumberActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_generates_sequential_registration_numbers_for_a_year(): void
    {
        $year = KcaYear::factory()->create([
            'code' => 'kca-2026',
            'name' => '2026 KCA Year',
        ]);
        $cohort = KcaCohort::factory()->for($year, 'year')->create();
        KcaEnrollment::factory()->for($year, 'year')->for($cohort, 'cohort')->create([
            'registration_number' => 'KCA-2026-00001',
        ]);

        $next = app(GenerateKcaRegistrationNumberAction::class)->handle($year);

        $this->assertSame('KCA-2026-00002', $next);
    }

    public function test_it_uses_calendar_year_when_no_year_is_provided(): void
    {
        $number = app(GenerateKcaRegistrationNumberAction::class)->handle();

        $this->assertMatchesRegularExpression('/^KCA-\d{4}-\d{5}$/', $number);
    }
}

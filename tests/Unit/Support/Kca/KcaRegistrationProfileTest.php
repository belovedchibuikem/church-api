<?php

namespace Tests\Unit\Support\Kca;

use App\Models\KcaApplication;
use App\Models\Person;
use App\Support\Kca\KcaRegistrationProfile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class KcaRegistrationProfileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_phone_falls_back_to_application_data_when_profile_phone_is_empty(): void
    {
        $person = Person::factory()->create();
        $person->load('profile');
        $person->profile?->forceFill(['phone' => null])->save();

        $application = new KcaApplication;
        $application->application_data = [
            'phone' => '+2348012345678',
            'mobile' => '+2348000000000',
        ];

        $flat = KcaRegistrationProfile::flattened($application, $person->fresh('profile'));

        $this->assertSame('+2348012345678', $flat['phone']);
    }

    public function test_phone_falls_back_to_mobile_when_phone_key_is_absent(): void
    {
        $person = Person::factory()->create();
        $person->load('profile');
        $person->profile?->forceFill(['phone' => null])->save();

        $application = new KcaApplication;
        $application->application_data = ['mobile' => '+2348098765432'];

        $flat = KcaRegistrationProfile::flattened($application, $person->fresh('profile'));

        $this->assertSame('+2348098765432', $flat['phone']);
    }
}

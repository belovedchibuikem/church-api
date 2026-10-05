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

    public function test_step_prefixed_answers_are_not_shown_twice(): void
    {
        $application = new KcaApplication;
        $application->application_data = KcaRegistrationProfile::normalizeIncoming([
            'church_ministry' => 'Family House of GOD',
            'step1_church_ministry' => 'Family House of GOD',
            'step1_church_address' => 'Maraba',
            'recommender_email' => 'pastor@example.org',
        ]);

        $sections = KcaRegistrationProfile::sections($application);
        $labels = [];
        foreach ($sections as $section) {
            foreach ($section['fields'] as $field) {
                $labels[] = $field['label'];
            }
        }

        $this->assertSame(1, count(array_filter($labels, fn (string $label): bool => $label === 'Church ministry')));
        $this->assertContains('Church address', $labels);
        $this->assertNotContains('Step1 church ministry', $labels);
        $this->assertContains('Church leader email', $labels);
    }

    public function test_stored_step_only_answers_display_once_under_plain_labels(): void
    {
        $application = new KcaApplication;
        $application->application_data = [
            'step1_church_ministry' => 'Family House of GOD',
            'step1_choice_index' => '2',
            'choice_index' => '2',
            'step2_why' => 'To serve',
        ];

        $labels = [];
        foreach (KcaRegistrationProfile::sections($application) as $section) {
            foreach ($section['fields'] as $field) {
                $labels[] = $field['label'];
            }
        }

        $this->assertContains('Church ministry', $labels);
        $this->assertSame([], array_values(array_filter($labels, fn (string $label): bool => str_contains(strtolower($label), 'step') || str_contains(strtolower($label), 'choice index'))));
    }

    public function test_normalizing_keeps_church_references(): void
    {
        $normalized = KcaRegistrationProfile::normalizeIncoming([
            'church_id' => 'church-public-id',
            'step1_home_church_id' => 'home-church-public-id',
            'password' => 'secret',
        ]);

        $this->assertSame('church-public-id', $normalized['church_id']);
        $this->assertSame('home-church-public-id', $normalized['home_church_id']);
        $this->assertArrayNotHasKey('password', $normalized);
    }
}

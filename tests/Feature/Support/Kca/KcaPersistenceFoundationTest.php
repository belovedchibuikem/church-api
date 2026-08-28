<?php

namespace Tests\Feature\Support\Kca;

use App\Kca\KcaAttendanceStatus;
use App\Kca\KcaPrerequisiteRequirement;
use App\Models\KcaApplication;
use App\Models\KcaAssessmentResult;
use App\Models\KcaAttendance;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaLecturerAssignment;
use App\Models\KcaLesson;
use App\Models\KcaMentorAssignment;
use App\Models\KcaModule;
use App\Models\KcaModulePrerequisite;
use App\Models\KcaYear;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class KcaPersistenceFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_student_enrollment_reuses_application_canonical_person(): void
    {
        $person = Person::factory()->create();
        $application = KcaApplication::factory()->accepted()->for($person)->create();
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create();

        $enrollment = KcaEnrollment::factory()
            ->for($application, 'application')
            ->for($person)
            ->for($year, 'year')
            ->for($cohort, 'cohort')
            ->create();

        $this->assertSame($person->getKey(), $application->person_id);
        $this->assertSame($person->getKey(), $enrollment->person_id);
        $this->assertSame($application->getKey(), $enrollment->kca_application_id);
        $this->assertTrue(Str::isUlid($enrollment->public_id));
    }

    public function test_curriculum_learning_and_distinct_mentor_lecturer_assignments_persist(): void
    {
        $enrollment = KcaEnrollment::factory()->create();
        $cohort = KcaCohort::query()->findOrFail($enrollment->kca_cohort_id);
        $actor = User::factory()->create();
        $mentor = Person::factory()->create();
        $lecturer = Person::factory()->create();
        $firstModule = KcaModule::factory()->create(['sequence' => 1]);
        $secondModule = KcaModule::factory()->create(['sequence' => 2]);
        $lesson = KcaLesson::factory()->for($secondModule, 'module')->create(['sequence' => 1]);
        $prerequisite = KcaModulePrerequisite::factory()
            ->for($secondModule, 'module')
            ->for($firstModule, 'prerequisiteModule')
            ->create(['requirement' => KcaPrerequisiteRequirement::PreviousModuleComplete]);
        $mentorAssignment = KcaMentorAssignment::factory()
            ->for($enrollment, 'enrollment')
            ->for($mentor, 'mentor')
            ->for($actor, 'assignedBy')
            ->create();
        $lecturerAssignment = KcaLecturerAssignment::factory()
            ->for($secondModule, 'module')
            ->for($cohort, 'cohort')
            ->for($lecturer, 'lecturer')
            ->for($actor, 'assignedBy')
            ->create();
        $attendance = KcaAttendance::factory()
            ->for($enrollment, 'enrollment')
            ->for($lesson, 'lesson')
            ->for($actor, 'recordedBy')
            ->create(['status' => KcaAttendanceStatus::Present]);
        $assessment = KcaAssessmentResult::factory()
            ->for($enrollment, 'enrollment')
            ->for($secondModule, 'module')
            ->for($actor, 'assessedBy')
            ->create([
                'assessment_code' => 'written_assessment_1',
                'result_code' => 'recorded',
                'score' => '71.50',
            ]);

        $this->assertSame($firstModule->getKey(), $prerequisite->prerequisite_module_id);
        $this->assertSame(KcaPrerequisiteRequirement::PreviousModuleComplete, $prerequisite->requirement);
        $this->assertSame($mentor->getKey(), $mentorAssignment->mentor_person_id);
        $this->assertSame($lecturer->getKey(), $lecturerAssignment->lecturer_person_id);
        $this->assertNotSame($mentorAssignment->mentor_person_id, $lecturerAssignment->lecturer_person_id);
        $this->assertSame(KcaAttendanceStatus::Present, $attendance->status);
        $this->assertSame('71.50', $assessment->score);
        $this->assertSame('recorded', $assessment->result_code);
    }
}

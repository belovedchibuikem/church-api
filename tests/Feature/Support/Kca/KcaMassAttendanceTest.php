<?php

namespace Tests\Feature\Support\Kca;

use App\Kca\KcaAttendanceStatus;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\User;
use App\Support\Kca\RecordKcaMassAttendanceAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class KcaMassAttendanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_mass_attendance_marks_every_student_for_a_lesson(): void
    {
        $actor = User::factory()->create();
        $lesson = KcaLesson::factory()->create();
        $first = KcaEnrollment::factory()->create();
        $second = KcaEnrollment::factory()->create();

        $result = $this->app->make(RecordKcaMassAttendanceAction::class)->handle(
            $lesson,
            now()->toImmutable(),
            [
                ['enrollment_id' => $first->public_id, 'status' => KcaAttendanceStatus::Present->value],
                ['enrollment_id' => $second->public_id, 'status' => KcaAttendanceStatus::Absent->value],
            ],
            $actor,
        );

        $this->assertSame(2, $result['recorded']);
        $this->assertSame(0, $result['updated']);
        $this->assertCount(2, $result['rows']);

        $roster = $this->app->make(RecordKcaMassAttendanceAction::class)->roster(
            $lesson,
            now()->toImmutable(),
        );
        $this->assertTrue($roster->contains(fn (array $row): bool => $row['enrollment_id'] === $first->public_id && $row['status'] === 'present'));
        $this->assertTrue($roster->contains(fn (array $row): bool => $row['enrollment_id'] === $second->public_id && $row['status'] === 'absent'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Bible\BibleCanon;
use App\Support\Bible\BibleReadingPlanGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BibleApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_bible_catalogue_and_chapter_are_readable(): void
    {
        $this->getJson('/api/v1/bible/books')
            ->assertOk()
            ->assertJsonPath('data.version.id', 'kjv')
            ->assertJsonPath('data.books.0.slug', 'genesis')
            ->assertJsonPath('data.books.42.slug', 'john');

        $this->getJson('/api/v1/bible/versions')
            ->assertOk()
            ->assertJsonPath('data.default', 'kjv')
            ->assertJsonPath('data.versions.0.id', 'kjv')
            ->assertJsonPath('data.versions.0.available', true)
            ->assertJsonPath('data.versions.1.id', 'niv');

        $this->getJson('/api/v1/bible/books/john/chapters/3?version=niv')
            ->assertStatus(422);

        $this->getJson('/api/v1/bible/search?q=faith')
            ->assertOk()
            ->assertJsonPath('data.results.0.text', fn (mixed $value): bool => is_string($value) && str_contains(strtolower($value), 'faith'));

        $this->getJson('/api/v1/bible/books/john/chapters/3')
            ->assertOk()
            ->assertJsonPath('data.book.name', 'John')
            ->assertJsonPath('data.chapter', 3)
            ->assertJsonPath('data.verses.15.text', fn (mixed $value): bool => is_string($value) && str_contains(strtolower($value), 'god so loved'));

        $this->getJson('/api/v1/bible/search?q=John%203:16')
            ->assertOk()
            ->assertJsonPath('data.results.0.reference', 'John 3:16');

        $this->getJson('/api/v1/bible/plans')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'month_3')
            ->assertJsonPath('data.2.code', 'year_1');

        $plan = $this->getJson('/api/v1/bible/plans/year_1')->assertOk()->json('data');
        $this->assertSame(365, $plan['days']);
        $this->assertCount(365, $plan['passages_per_day']);
        $this->assertSame(BibleCanon::chapterCount(), array_sum(array_map('count', $plan['passages_per_day'])));

        $sprint = $this->getJson('/api/v1/bible/plans/month_3')->assertOk()->json('data');
        $this->assertSame(90, $sprint['days']);
        $this->assertCount(90, $sprint['passages_per_day']);

        $custom = $this->getJson('/api/v1/bible/plans/days_120')->assertOk()->json('data');
        $this->assertSame(120, $custom['days']);
    }

    public function test_user_can_enroll_in_a_plan_and_complete_the_daily_target(): void
    {
        $user = User::factory()->withPerson()->create();
        $this->authenticate($user);

        $this->getJson('/api/v1/user/bible/progress')
            ->assertOk()
            ->assertJsonPath('data.enrollment', null);

        $this->postJson('/api/v1/user/bible/enrollments', [
            'plan_code' => 'year_1',
            'started_on' => now('Africa/Lagos')->toDateString(),
            'timezone' => 'Africa/Lagos',
        ])->assertCreated()
            ->assertJsonPath('data.enrollment.plan_code', 'year_1')
            ->assertJsonPath('data.enrollment.due.day_number', 1)
            ->assertJsonPath('data.enrollment.is_catching_up', false);

        $progress = $this->getJson('/api/v1/user/bible/progress')->assertOk()->json('data');
        $enrollmentId = $progress['enrollment']['id'];
        $dueDay = $progress['enrollment']['due']['day_number'];
        $this->assertNotEmpty($progress['enrollment']['due']['passages']);

        $this->postJson("/api/v1/user/bible/enrollments/{$enrollmentId}/days/{$dueDay}/complete")
            ->assertOk()
            ->assertJsonPath('data.enrollment.completed_days', 1);

        $this->putJson('/api/v1/user/bible/position', [
            'book' => 'john',
            'chapter' => 3,
        ])->assertOk()
            ->assertJsonPath('data.position.book_slug', 'john')
            ->assertJsonPath('data.position.chapter', 3);

        $this->postJson('/api/v1/user/bible/enrollments', [
            'duration_days' => 90,
            'started_on' => now('Africa/Lagos')->toDateString(),
            'timezone' => 'Africa/Lagos',
        ])->assertCreated()
            ->assertJsonPath('data.enrollment.plan_code', 'days_90')
            ->assertJsonPath('data.enrollment.day_count', 90);

        $this->assertSame(90, BibleReadingPlanGenerator::plan('month_3')['days']);
        $this->assertSame(180, BibleReadingPlanGenerator::plan('month_6')['days']);
        $this->assertSame(365, BibleReadingPlanGenerator::plan('year_1')['days']);
        $this->assertSame(730, BibleReadingPlanGenerator::plan('year_2')['days']);
        $this->assertSame(1095, BibleReadingPlanGenerator::plan('year_3')['days']);
    }

    private function authenticate(User $user, bool $recentMfa = false): SecuritySession
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $session = ['security_session_id' => $securitySession->public_id];

        if ($recentMfa) {
            $session['auth.mfa_verified_at'] = now()->utc()->toIso8601String();
        }

        $this->actingAs($user);
        $this->withSession($session);

        return $securitySession;
    }
}

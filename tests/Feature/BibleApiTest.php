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
            ->assertJsonPath('data.0.code', 'year_1');

        $plan = $this->getJson('/api/v1/bible/plans/year_1')->assertOk()->json('data');
        $this->assertSame(365, $plan['days']);
        $this->assertCount(365, $plan['passages_per_day']);
        $this->assertSame(BibleCanon::chapterCount(), array_sum(array_map('count', $plan['passages_per_day'])));
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

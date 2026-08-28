<?php

namespace Tests\Feature\Support\Identity;

use App\Exceptions\UserAccountStateConflictException;
use App\Identity\UserAccountStatus;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Identity\SuspendUserAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class SuspendUserActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_suspends_an_active_account_and_records_the_reason(): void
    {
        $this->freezeSecond();
        $actor = User::factory()->create();
        $user = User::factory()->create();

        $suspendedUser = $this->app->make(SuspendUserAction::class)
            ->handle($user, 'security.compromise', $actor);

        $this->assertTrue($suspendedUser->isSuspended());
        $this->assertSame(UserAccountStatus::Suspended, $suspendedUser->account_status);
        $this->assertSame('security.compromise', $suspendedUser->suspension_reason);
        $this->assertTrue($suspendedUser->suspended_at->equalTo(now()));
        $this->assertNull($suspendedUser->reactivated_at);
        $this->assertSame($user->getKey(), User::query()->suspended()->sole()->getKey());

        $auditEvent = AuditEvent::query()->sole();
        $this->assertSame('identity.user.suspended', $auditEvent->action);
        $this->assertSame($actor->getKey(), $auditEvent->actor_user_id);
        $this->assertSame(['reason' => 'security.compromise'], $auditEvent->metadata);
    }

    public function test_repeating_the_same_suspension_is_idempotent(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $action = $this->app->make(SuspendUserAction::class);

        $action->handle($user, 'policy.violation', $actor);
        $suspendedUser = $action->handle($user, 'policy.violation', $actor);

        $this->assertTrue($suspendedUser->isSuspended());
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_rejects_overwriting_an_active_suspension_reason(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $action = $this->app->make(SuspendUserAction::class);
        $action->handle($user, 'policy.violation', $actor);
        $wasRejected = false;

        try {
            $action->handle($user, 'security.compromise', $actor);
            $this->fail('Expected the suspension reason conflict to be rejected.');
        } catch (UserAccountStateConflictException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame('policy.violation', $user->fresh()->suspension_reason);
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_account_state_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();

        $user->fill([
            'account_status' => UserAccountStatus::Suspended->value,
            'suspension_reason' => 'unsafe.override',
        ]);

        $this->assertSame(UserAccountStatus::Active, $user->account_status);
        $this->assertNull($user->suspension_reason);
        $this->assertFalse($user->isDirty('account_status'));
    }

    public function test_rejects_an_unstable_suspension_reason_without_changing_the_account(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $wasRejected = false;

        try {
            $this->app->make(SuspendUserAction::class)
                ->handle($user, 'Free-form reason with personal details', $actor);
            $this->fail('Expected the unstable suspension reason to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertFalse($user->fresh()->isSuspended());
        $this->assertSame(0, AuditEvent::query()->count());
    }
}

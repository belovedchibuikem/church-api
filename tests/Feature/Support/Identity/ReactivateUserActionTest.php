<?php

namespace Tests\Feature\Support\Identity;

use App\Identity\UserAccountStatus;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Identity\ReactivateUserAction;
use App\Support\Identity\SuspendUserAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReactivateUserActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reactivates_a_suspended_account_without_discarding_the_suspension_timestamp(): void
    {
        $this->freezeSecond();
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $suspendedUser = $this->app->make(SuspendUserAction::class)
            ->handle($user, 'security.compromise', $actor);
        $suspendedAt = $suspendedUser->suspended_at;

        $reactivatedUser = $this->app->make(ReactivateUserAction::class)
            ->handle($suspendedUser, $actor);

        $this->assertFalse($reactivatedUser->isSuspended());
        $this->assertSame(UserAccountStatus::Active, $reactivatedUser->account_status);
        $this->assertNull($reactivatedUser->suspension_reason);
        $this->assertTrue($reactivatedUser->suspended_at->equalTo($suspendedAt));
        $this->assertTrue($reactivatedUser->reactivated_at->equalTo(now()));
        $this->assertSame(
            ['identity.user.suspended', 'identity.user.reactivated'],
            AuditEvent::query()->orderBy('id')->pluck('action')->all(),
        );

        $reactivation = AuditEvent::query()->latest('id')->firstOrFail();
        $this->assertSame($actor->getKey(), $reactivation->actor_user_id);
        $this->assertSame(
            ['previous_suspension_reason' => 'security.compromise'],
            $reactivation->metadata,
        );
    }

    public function test_reactivating_an_active_account_is_idempotent(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();

        $activeUser = $this->app->make(ReactivateUserAction::class)->handle($user, $actor);

        $this->assertSame(UserAccountStatus::Active, $activeUser->account_status);
        $this->assertSame(0, AuditEvent::query()->count());
    }
}

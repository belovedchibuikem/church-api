<?php

namespace App\Support\Church;

use App\Church\ChurchMembershipStatus;
use App\Church\HomeChurchStatus;
use App\Church\MembershipJoinIntent;
use App\Exceptions\MembershipTransferRequiredException;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\HomeChurch;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StartChurchMembershipAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private EndChurchMembershipAction $endMembership,
    ) {}

    public function handle(
        Person $person,
        Church $church,
        ?HomeChurch $homeChurch = null,
        ?CarbonInterface $joinedAt = null,
        ?User $actor = null,
        MembershipJoinIntent $intent = MembershipJoinIntent::Admin,
        bool $confirmTransfer = false,
    ): ChurchMembership {
        return DB::transaction(function () use (
            $person,
            $church,
            $homeChurch,
            $joinedAt,
            $actor,
            $intent,
            $confirmTransfer,
        ): ChurchMembership {
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
            $lockedHomeChurch = $homeChurch === null
                ? null
                : HomeChurch::query()->lockForUpdate()->findOrFail($homeChurch->getKey());
            $this->assertHomeChurchMembershipIsValid($lockedChurch, $lockedHomeChurch);

            if ($intent === MembershipJoinIntent::HomeChurch) {
                if ($lockedHomeChurch === null) {
                    throw new InvalidArgumentException('A home church is required to join a home church.');
                }

                return $this->joinHomeChurch(
                    $lockedPerson,
                    $lockedChurch,
                    $lockedHomeChurch,
                    $joinedAt,
                    $actor,
                    $confirmTransfer,
                );
            }

            if ($intent === MembershipJoinIntent::Conventional) {
                $this->transferConventionalIfNeeded(
                    $lockedPerson,
                    $lockedChurch,
                    $confirmTransfer,
                    $actor,
                );
            }

            $existing = ChurchMembership::query()
                ->whereBelongsTo($lockedPerson)
                ->whereBelongsTo($lockedChurch)
                ->where('active_marker', 1)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($lockedHomeChurch === null) {
                    throw new InvalidArgumentException('The person already has an active membership at this church.');
                }

                if ((int) $existing->home_church_id !== (int) $lockedHomeChurch->getKey()) {
                    $existing->home_church_id = $lockedHomeChurch->getKey();
                    $existing->save();
                }

                return $existing;
            }

            return $this->createMembership(
                $lockedPerson,
                $lockedChurch,
                $lockedHomeChurch,
                $joinedAt,
                $actor,
            );
        }, attempts: 3);
    }

    private function joinHomeChurch(
        Person $person,
        Church $church,
        HomeChurch $homeChurch,
        ?CarbonInterface $joinedAt,
        ?User $actor,
        bool $confirmTransfer,
    ): ChurchMembership {
        $alreadyHere = ChurchMembership::query()
            ->whereBelongsTo($person)
            ->where('home_church_id', $homeChurch->getKey())
            ->where('active_marker', 1)
            ->lockForUpdate()
            ->first();

        if ($alreadyHere !== null) {
            return $alreadyHere;
        }

        $otherHome = ChurchMembership::query()
            ->with('homeChurch:id,public_id,name')
            ->whereBelongsTo($person)
            ->whereNotNull('home_church_id')
            ->where('home_church_id', '!=', $homeChurch->getKey())
            ->where('active_marker', 1)
            ->lockForUpdate()
            ->first();

        if ($otherHome !== null) {
            if (! $confirmTransfer) {
                throw MembershipTransferRequiredException::homeChurch(
                    (string) $otherHome->homeChurch?->public_id,
                    (string) ($otherHome->homeChurch?->name ?? 'your current home church'),
                    $homeChurch->public_id,
                    $homeChurch->name,
                );
            }

            $this->releaseHomeChurchMembership($otherHome, $person, $church, $actor);
        }

        $parentMembership = ChurchMembership::query()
            ->whereBelongsTo($person)
            ->whereBelongsTo($church)
            ->where('active_marker', 1)
            ->lockForUpdate()
            ->first();

        if ($parentMembership !== null) {
            $parentMembership->home_church_id = $homeChurch->getKey();
            $parentMembership->save();

            return $parentMembership;
        }

        return $this->createMembership($person, $church, $homeChurch, $joinedAt, $actor);
    }

    private function transferConventionalIfNeeded(
        Person $person,
        Church $targetChurch,
        bool $confirmTransfer,
        ?User $actor,
    ): void {
        $other = ChurchMembership::query()
            ->with('church:id,public_id,name')
            ->whereBelongsTo($person)
            ->where('church_id', '!=', $targetChurch->getKey())
            ->whereNull('home_church_id')
            ->where('active_marker', 1)
            ->lockForUpdate()
            ->get();

        if ($other->isEmpty()) {
            return;
        }

        $current = $other->first();
        if (! $confirmTransfer) {
            throw MembershipTransferRequiredException::conventional(
                (string) $current?->church?->public_id,
                (string) ($current?->church?->name ?? 'your current church'),
                $targetChurch->public_id,
                $targetChurch->name,
            );
        }

        foreach ($other as $membership) {
            if ($actor !== null) {
                $this->endMembership->handle($membership, 'member_transferred', $actor);
            } else {
                $membership->status = ChurchMembershipStatus::Ended;
                $membership->active_marker = null;
                $membership->ended_at = now()->utc();
                $membership->end_reason_code = 'member_transferred';
                $membership->save();
            }
        }
    }

    private function releaseHomeChurchMembership(
        ChurchMembership $membership,
        Person $person,
        Church $incomingParent,
        ?User $actor,
    ): void {
        $keepAsConventional = ChurchMembership::query()
            ->whereBelongsTo($person)
            ->where('active_marker', 1)
            ->where('id', '!=', $membership->getKey())
            ->where(function ($query) use ($incomingParent): void {
                $query->whereNull('home_church_id')
                    ->orWhere('church_id', '!=', $incomingParent->getKey());
            })
            ->exists();

        if ($keepAsConventional && (int) $membership->church_id !== (int) $incomingParent->getKey()) {
            if ($actor !== null) {
                $this->endMembership->handle($membership, 'member_transferred', $actor);
            } else {
                $membership->status = ChurchMembershipStatus::Ended;
                $membership->active_marker = null;
                $membership->ended_at = now()->utc();
                $membership->end_reason_code = 'member_transferred';
                $membership->save();
            }

            return;
        }

        $membership->home_church_id = null;
        $membership->save();
    }

    private function createMembership(
        Person $person,
        Church $church,
        ?HomeChurch $homeChurch,
        ?CarbonInterface $joinedAt,
        ?User $actor,
    ): ChurchMembership {
        $membership = new ChurchMembership([
            'person_id' => $person->getKey(),
            'church_id' => $church->getKey(),
            'home_church_id' => $homeChurch?->getKey(),
            'joined_at' => ($joinedAt ?? now())->utc(),
        ]);
        $membership->status = ChurchMembershipStatus::Active;
        $membership->active_marker = 1;
        $membership->ended_at = null;
        $membership->end_reason_code = null;
        $membership->save();

        $this->recordAuditEvent->handle(new AuditEventData(
            action: 'church.membership.started',
            actor: $actor,
            targetType: 'church_membership',
            targetId: $membership->public_id,
            scopeType: $homeChurch === null ? 'church' : 'home_church',
            scopeId: $homeChurch?->public_id ?? $church->public_id,
            metadata: ['person_id' => $person->public_id],
        ));

        return $membership;
    }

    private function assertHomeChurchMembershipIsValid(
        Church $church,
        ?HomeChurch $homeChurch,
    ): void {
        if ($homeChurch === null) {
            return;
        }

        if (
            $homeChurch->church_id !== $church->getKey()
            || $homeChurch->status !== HomeChurchStatus::Active
        ) {
            throw new InvalidArgumentException('Membership requires an active Home Church belonging to the church.');
        }
    }
}

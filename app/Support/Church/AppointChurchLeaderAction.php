<?php

namespace App\Support\Church;

use App\Models\Church;
use App\Models\ChurchRoleAssignment;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AppointChurchLeaderAction
{
    public function __construct(
        private StartChurchMembershipAction $startMembership,
        private ProvisionPersonAdminAccessAction $provisionAdminAccess,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     started_at?: CarbonInterface|null,
     *     grant_admin_access?: bool,
     *     admin_email?: string|null,
     * }  $options
     */
    public function handle(
        Person $person,
        Church $church,
        array $options,
        ?User $actor = null,
    ): ChurchRoleAssignment {
        $title = trim($options['title']);
        if (! ChurchLeadershipCatalog::isValidTitle($title)) {
            throw new InvalidArgumentException('Choose a leadership title from the approved list.');
        }

        return DB::transaction(function () use ($person, $church, $title, $options, $actor): ChurchRoleAssignment {
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());

            $activeCount = ChurchRoleAssignment::query()
                ->where('church_id', $lockedChurch->getKey())
                ->where('role_type', 'leader')
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->count();

            if ($activeCount >= ChurchLeadershipCatalog::MAX_ACTIVE_LEADERS_PER_CHURCH) {
                throw new InvalidArgumentException(sprintf(
                    'This church already has the maximum of %d active leaders.',
                    ChurchLeadershipCatalog::MAX_ACTIVE_LEADERS_PER_CHURCH,
                ));
            }

            $duplicate = ChurchRoleAssignment::query()
                ->where('church_id', $lockedChurch->getKey())
                ->where('person_id', $lockedPerson->getKey())
                ->where('role_type', 'leader')
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->exists();

            if ($duplicate) {
                throw new InvalidArgumentException('This person already has an active leadership assignment at this church.');
            }

            $this->startMembership->handle(
                $lockedPerson,
                $lockedChurch,
                actor: $actor,
                confirmTransfer: true,
            );

            $assignment = ChurchRoleAssignment::query()->create([
                'church_id' => $lockedChurch->getKey(),
                'person_id' => $lockedPerson->getKey(),
                'role_type' => 'leader',
                'title' => $title,
                'status' => 'active',
                'started_at' => ($options['started_at'] ?? now())->utc(),
            ]);

            if ($options['grant_admin_access'] ?? false) {
                $this->provisionAdminAccess->handle(
                    $lockedPerson,
                    $lockedChurch,
                    $actor,
                    $options['admin_email'] ?? null,
                );
            }

            return $assignment;
        }, attempts: 3);
    }
}

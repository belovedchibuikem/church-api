<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Mission\CrusadeStatus;
use App\Mission\MissionInvitationStatus;
use App\Models\Church;
use App\Models\ChurchAnnouncement;
use App\Models\ChurchDepartment;
use App\Models\ChurchGroup;
use App\Models\ChurchMembership;
use App\Models\ChurchRoleAssignment;
use App\Models\Convert;
use App\Models\CounsellingCase;
use App\Models\Crusade;
use App\Models\EvangelismActivity;
use App\Models\FirstTimer;
use App\Models\FollowUpInteraction;
use App\Models\FollowUpTask;
use App\Models\HomeChurch;
use App\Models\HomeChurchApplication;
use App\Models\HomeChurchAttendanceRecord;
use App\Models\MentorAssignment;
use App\Models\MissionInvitation;
use App\Models\MissionSoulJourney;
use App\Models\MissionTeamAssignment;
use App\Models\PastoralNeed;
use App\Models\Person;
use App\Models\PrayerRequest;
use App\Models\SafeguardingIncident;
use App\Models\Testimony;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class ProtectedDomainRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return match (true) {
            $this->resource instanceof Church => $this->church(),
            $this->resource instanceof HomeChurch => $this->homeChurch(),
            $this->resource instanceof HomeChurchApplication => $this->homeChurchApplication(),
            $this->resource instanceof FirstTimer => $this->firstTimer(),
            $this->resource instanceof FollowUpTask => $this->followUpTask(),
            $this->resource instanceof Crusade => $this->crusade(),
            $this->resource instanceof MissionSoulJourney => $this->missionSoulJourney(),
            $this->resource instanceof MentorAssignment => $this->mentorAssignment(),
            $this->resource instanceof FollowUpInteraction => $this->followUpInteraction(),
            $this->resource instanceof ChurchMembership => $this->churchMembership(),
            $this->resource instanceof MissionInvitation => $this->missionInvitation(),
            $this->resource instanceof MissionTeamAssignment => $this->missionTeamAssignment(),
            $this->resource instanceof PrayerRequest => $this->prayerRequest(),
            $this->resource instanceof PastoralNeed => $this->pastoralNeed(),
            $this->resource instanceof Convert => $this->convert(),
            $this->resource instanceof EvangelismActivity => $this->evangelismActivity(),
            $this->resource instanceof ChurchDepartment => $this->churchDepartment(),
            $this->resource instanceof ChurchRoleAssignment => $this->churchRoleAssignment(),
            $this->resource instanceof CounsellingCase => $this->counsellingCase(),
            $this->resource instanceof Testimony => $this->testimony(),
            $this->resource instanceof HomeChurchAttendanceRecord => $this->homeChurchAttendance(),
            $this->resource instanceof ChurchGroup => $this->churchGroup(),
            $this->resource instanceof ChurchAnnouncement => $this->churchAnnouncement(),
            $this->resource instanceof Person => $this->personDirectory(),
            $this->resource instanceof SafeguardingIncident => $this->safeguardingIncident(),
            default => throw new LogicException('Unsupported protected domain resource.'),
        };
    }

    /** @return array<string, mixed> */
    private function church(): array
    {
        $location = $this->location;

        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'location_id' => $location?->public_id,
            'location_name' => $location?->name,
            'country_id' => $location?->country?->public_id,
            'country_name' => $location?->country?->name,
            'address_line_one' => $location?->address_line_one,
            'address_line_two' => $location?->address_line_two,
            'locality' => $location?->locality,
            'postal_code' => $location?->postal_code,
            'timezone' => $location?->timezone,
            'administrative_unit_id' => $this->administrativeUnit?->public_id,
            'administrative_unit_name' => $this->administrativeUnit?->name,
            'status' => $this->published_at ? 'published' : 'unpublished',
            'home_churches_count' => (int) ($this->home_churches_count ?? 0),
            'memberships_count' => (int) ($this->memberships_count ?? 0),
            'first_timers_count' => (int) ($this->first_timers_count ?? 0),
            'applications_count' => (int) ($this->home_church_applications_count ?? 0),
            'published_at' => $this->published_at?->utc()->toIso8601String(),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function homeChurch(): array
    {
        return [
            'id' => $this->public_id, 'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'leader_person_id' => $this->leader?->public_id,
            'leader_name' => PersonDisplayName::of($this->leader),
            'location_id' => $this->location?->public_id,
            'location_name' => $this->location?->name,
            'administrative_unit_id' => $this->administrativeUnit?->public_id,
            'administrative_unit_name' => $this->administrativeUnit?->name,
            'name' => $this->name,
            'meeting_schedules' => $this->meeting_schedules ?? [],
            'status' => $this->status->value,
            'members_count' => (int) ($this->memberships_count ?? 0),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function homeChurchApplication(): array
    {
        $applicant = $this->applicant;

        return [
            'id' => $this->public_id, 'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'home_church_id' => $this->homeChurch?->public_id,
            'applicant_person_id' => $applicant?->public_id ?? $this->applicant()->value('public_id'),
            'applicant_name' => PersonDisplayName::of($applicant),
            'proposed_name' => $this->proposed_name,
            'residence_family_name' => $this->residence_family_name,
            'expected_participants' => $this->expected_participants,
            'meeting_day' => $this->meeting_day->value,
            'meeting_time' => $this->meeting_time,
            'meeting_schedules' => $this->meeting_schedules ?? [],
            'status' => $this->status->value,
            'status_changed_at' => $this->status_changed_at?->utc()->toIso8601String(),
            'location_id' => $this->location?->public_id,
            'location_name' => $this->location?->name,
            'administrative_unit_id' => $this->administrativeUnit?->public_id,
            'administrative_unit_name' => $this->administrativeUnit?->name,
            'allowed_actions' => $this->status->allowedActions(),
            'history' => $this->whenLoaded('transitions', function () {
                return $this->transitions->map(fn ($row) => [
                    'id' => $row->public_id,
                    'from_status' => $row->from_status->value,
                    'to_status' => $row->to_status->value,
                    'reason_code' => $row->reason_code,
                    'notes' => $row->notes ?? null,
                    'occurred_at' => $row->occurred_at?->utc()->toIso8601String(),
                ])->all();
            }),
        ];
    }

    /** @return array<string, mixed> */
    private function firstTimer(): array
    {
        $person = $this->person;

        return [
            'id' => $this->public_id,
            'person_id' => $person?->public_id ?? $this->person()->value('public_id'),
            'person_name' => PersonDisplayName::of($person),
            'person_email' => PersonDisplayName::email($person),
            'person_phone' => PersonDisplayName::phone($person),
            'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'home_church_id' => $this->homeChurch?->public_id,
            'home_church_name' => $this->homeChurch?->name,
            'registered_at' => $this->registered_at?->utc()->toIso8601String(),
            'contacted_at' => $this->contacted_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function followUpTask(): array
    {
        $assignee = $this->assignedTo;
        $firstTimer = $this->firstTimer;

        return [
            'id' => $this->public_id, 'first_timer_id' => $firstTimer?->public_id,
            'person_name' => PersonDisplayName::of($firstTimer?->person),
            'assigned_to_person_id' => $assignee?->public_id ?? $this->assignedTo()->value('public_id'),
            'assigned_to_name' => PersonDisplayName::of($assignee),
            'type' => $this->type->value, 'status' => $this->status->value,
            'due_at' => $this->due_at?->utc()->toIso8601String(),
            'completed_at' => $this->completed_at?->utc()->toIso8601String(),
            'completion_reason_code' => $this->completion_reason_code,
        ];
    }

    /** @return array<string, mixed> */
    private function crusade(): array
    {
        return [
            'id' => $this->public_id, 'name' => $this->name,
            'code' => $this->code,
            'theme' => $this->theme,
            'purpose' => $this->purpose,
            'description' => $this->description,
            'timezone' => $this->timezone,
            'status' => $this->status instanceof CrusadeStatus ? $this->status->value : (string) $this->status,
            'location_id' => $this->location?->public_id,
            'location_name' => $this->location?->name,
            'starts_at' => $this->starts_at?->utc()->toIso8601String(),
            'ends_at' => $this->ends_at?->utc()->toIso8601String(),
            'published_at' => $this->published_at?->utc()->toIso8601String(),
            'archived_at' => $this->archived_at?->utc()->toIso8601String(),
            'souls_count' => (int) ($this->soul_journeys_count ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function missionSoulJourney(): array
    {
        $person = $this->person;

        return [
            'id' => $this->public_id, 'crusade_id' => $this->crusade?->public_id,
            'crusade_name' => $this->crusade?->name,
            'person_id' => $person?->public_id ?? $this->person()->value('public_id'),
            'person_name' => PersonDisplayName::of($person),
            'connected_church_id' => $this->connectedChurch?->public_id ?? $this->connectedChurch()->value('public_id'),
            'connected_church_name' => $this->connectedChurch?->name,
            'status' => $this->status->value, 'mentor_assignment_id' => $this->mentorAssignment?->public_id,
            'captured_at' => $this->captured_at?->utc()->toIso8601String(),
            'converted_at' => $this->converted_at?->utc()->toIso8601String(),
            'conversion_reason_code' => $this->conversion_reason_code,
            'last_follow_up_at' => $this->last_follow_up_at?->utc()->toIso8601String(),
            'follow_up_completed_at' => $this->follow_up_completed_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function mentorAssignment(): array
    {
        return [
            'id' => $this->public_id,
            'soul_id' => $this->soulJourney?->public_id ?? $this->soulJourney()->value('public_id'),
            'mission_team_assignment_id' => $this->teamAssignment?->public_id ?? $this->teamAssignment()->value('public_id'),
            'assigned_at' => $this->assigned_at?->utc()->toIso8601String(),
            'ended_at' => $this->ended_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function followUpInteraction(): array
    {
        return [
            'id' => $this->public_id,
            'soul_id' => $this->soulJourney?->public_id ?? $this->soulJourney()->value('public_id'),
            'mentor_assignment_id' => $this->mentorAssignment?->public_id ?? $this->mentorAssignment()->value('public_id'),
            'channel_code' => $this->channel_code, 'outcome_code' => $this->outcome_code,
            'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function churchMembership(): array
    {
        return [
            'id' => $this->public_id,
            'person_id' => $this->person?->public_id,
            'person_name' => PersonDisplayName::of($this->person),
            'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'home_church_id' => $this->homeChurch?->public_id,
            'home_church_name' => $this->homeChurch?->name,
            'status' => $this->status->value,
            'joined_at' => $this->joined_at?->utc()->toIso8601String(),
            'ended_at' => $this->ended_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function missionInvitation(): array
    {
        $status = $this->status instanceof MissionInvitationStatus
            ? $this->status
            : (MissionInvitationStatus::tryFrom((string) $this->status) ?? MissionInvitationStatus::Received);

        return [
            'id' => $this->public_id,
            'crusade_id' => $this->crusade?->public_id,
            'crusade_name' => $this->crusade?->name,
            'requester_person_id' => $this->requester?->public_id,
            'requester_name' => PersonDisplayName::of($this->requester),
            'requested_location_id' => $this->requestedLocation?->public_id,
            'requested_location_name' => $this->requestedLocation?->name,
            'purpose' => $this->purpose,
            'expected_attendance' => $this->expected_attendance,
            'notes' => $this->notes,
            'status' => $status->value,
            'allowed_transitions' => array_map(
                static fn (MissionInvitationStatus $item): string => $item->value,
                $status->allowedTargets(),
            ),
            'status_changed_at' => $this->status_changed_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function missionTeamAssignment(): array
    {
        $active = $this->ended_at === null || $this->ended_at->isFuture();

        return [
            'id' => $this->public_id,
            'crusade_id' => $this->crusade?->public_id,
            'crusade_name' => $this->crusade?->name,
            'person_id' => $this->person?->public_id,
            'person_name' => PersonDisplayName::of($this->person),
            'role_code' => $this->role_code,
            'name' => $this->role_code,
            'assigned_at' => $this->assigned_at?->utc()->toIso8601String(),
            'ended_at' => $this->ended_at?->utc()->toIso8601String(),
            'status' => $active ? 'Active' : 'Ended',
        ];
    }

    /** @return array<string, mixed> */
    private function prayerRequest(): array
    {
        return [
            'id' => $this->public_id,
            'person_id' => $this->person?->public_id,
            'person_name' => PersonDisplayName::of($this->person),
            'person_email' => PersonDisplayName::email($this->person),
            'assigned_to_person_id' => $this->assignedTo?->public_id,
            'assigned_to_name' => PersonDisplayName::of($this->assignedTo),
            'assigned_at' => $this->assigned_at?->utc()->toIso8601String(),
            'subject' => $this->subject,
            'status' => $this->status,
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function pastoralNeed(): array
    {
        return [
            'id' => $this->public_id,
            'person_id' => $this->person?->public_id,
            'person_name' => PersonDisplayName::of($this->person),
            'category' => $this->category,
            'status' => $this->status,
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function convert(): array
    {
        $person = $this->person;

        return [
            'id' => $this->public_id,
            'person_id' => $person?->public_id,
            'person_name' => PersonDisplayName::of($person),
            'person_email' => PersonDisplayName::email($person),
            'person_phone' => PersonDisplayName::phone($person),
            'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'home_church_id' => $this->homeChurch?->public_id,
            'home_church_name' => $this->homeChurch?->name,
            'converted_at' => $this->converted_at?->utc()->toIso8601String(),
            'baptized_at' => $this->baptized_at?->utc()->toIso8601String(),
            'source' => $this->source,
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }

    /** @return array<string, mixed> */
    private function evangelismActivity(): array
    {
        return [
            'id' => $this->public_id,
            'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'title' => $this->title,
            'name' => $this->title,
            'activity_type' => $this->activity_type,
            'souls_reached' => $this->souls_reached,
            'decisions' => $this->decisions,
            'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }

    /** @return array<string, mixed> */
    private function churchDepartment(): array
    {
        return [
            'id' => $this->public_id,
            'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'name' => $this->name,
            'description' => $this->description,
            'leader_person_id' => $this->leader?->public_id,
            'leader_name' => PersonDisplayName::of($this->leader),
            'status' => $this->status,
        ];
    }

    /** @return array<string, mixed> */
    private function churchRoleAssignment(): array
    {
        $person = $this->person;

        return [
            'id' => $this->public_id,
            'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'person_id' => $person?->public_id,
            'person_name' => PersonDisplayName::of($person),
            'person_phone' => PersonDisplayName::phone($person),
            'department_id' => $this->department?->public_id,
            'department_name' => $this->department?->name,
            'role_type' => $this->role_type,
            'title' => $this->title,
            'name' => $this->title,
            'status' => $this->status,
            'started_at' => $this->started_at?->utc()->toIso8601String(),
            'ended_at' => $this->ended_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function counsellingCase(): array
    {
        return [
            'id' => $this->public_id,
            'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'client_label' => 'Restricted',
            'counselor_label' => $this->counselor ? 'Assigned' : 'Unassigned',
            'case_type' => $this->case_type,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->utc()->toIso8601String(),
            'closed_at' => $this->closed_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function testimony(): array
    {
        return [
            'id' => $this->public_id,
            'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'person_id' => $this->person?->public_id,
            'person_name' => PersonDisplayName::of($this->person),
            'title' => $this->title,
            'name' => $this->title,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->utc()->toIso8601String(),
            'published_at' => $this->published_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function homeChurchAttendance(): array
    {
        $total = (int) $this->adults + (int) $this->children + (int) $this->first_timers;

        return [
            'id' => $this->public_id,
            'home_church_id' => $this->homeChurch?->public_id,
            'home_church_name' => $this->homeChurch?->name,
            'church_name' => $this->homeChurch?->name,
            'service_date' => $this->service_date?->toDateString(),
            'adults' => $this->adults,
            'children' => $this->children,
            'first_timers' => $this->first_timers,
            'total' => $total,
            'notes' => $this->notes,
            'name' => ($this->homeChurch?->name ?? 'Attendance').' · '.($this->service_date?->toDateString() ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private function churchGroup(): array
    {
        return [
            'id' => $this->public_id,
            'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'name' => $this->name,
            'description' => $this->description,
            'leader_person_id' => $this->leader?->public_id,
            'leader_name' => PersonDisplayName::of($this->leader),
            'capacity' => $this->capacity,
            'status' => $this->is_published ? 'Active' : 'Unpublished',
            'is_published' => $this->is_published,
        ];
    }

    /** @return array<string, mixed> */
    private function churchAnnouncement(): array
    {
        return [
            'id' => $this->public_id,
            'church_id' => $this->church?->public_id,
            'church_name' => $this->church?->name,
            'title' => $this->title,
            'name' => $this->title,
            'body' => $this->body,
            'published_at' => $this->published_at?->utc()->toIso8601String(),
            'created_by_person_id' => $this->createdBy?->public_id,
            'created_by_name' => PersonDisplayName::of($this->createdBy),
            'status' => $this->published_at ? 'Published' : 'Draft',
        ];
    }

    /** @return array<string, mixed> */
    private function personDirectory(): array
    {
        $membership = $this->relationLoaded('memberships') ? $this->memberships->first() : null;
        $roles = [];
        if ($this->relationLoaded('memberships') && $this->memberships->contains(function ($row): bool {
            $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;

            return $status === 'active';
        })) {
            $roles[] = 'Member';
        }
        if ($this->relationLoaded('firstTimers') && $this->firstTimers->isNotEmpty()) {
            $roles[] = 'First Timer';
        }
        if ($this->relationLoaded('converts') && $this->converts->isNotEmpty()) {
            $roles[] = 'Convert';
        }
        if ($this->relationLoaded('roleAssignments')) {
            foreach ($this->roleAssignments as $assignment) {
                $label = match ($assignment->role_type) {
                    'leader' => 'Leader',
                    'worker' => 'Worker',
                    'disciple' => 'Disciple',
                    default => (string) $assignment->role_type,
                };
                if ($label !== '' && ! in_array($label, $roles, true)) {
                    $roles[] = $label;
                }
            }
        }
        if ($roles === []) {
            $roles[] = $this->user ? 'Account' : 'Person';
        }

        $status = $this->archived_at ? 'archived' : ($membership?->status instanceof \BackedEnum
            ? $membership->status->value
            : ($membership?->status ?? ($this->user?->account_status?->value ?? $this->user?->account_status ?? 'active')));

        return [
            'id' => $this->public_id,
            'name' => PersonDisplayName::of($this->resource) ?: 'Not provided',
            'person_name' => PersonDisplayName::of($this->resource) ?: 'Not provided',
            'email' => PersonDisplayName::email($this->resource),
            'person_email' => PersonDisplayName::email($this->resource),
            'phone' => PersonDisplayName::phone($this->resource),
            'person_phone' => PersonDisplayName::phone($this->resource),
            'preferred_name' => $this->profile?->preferred_name,
            'given_name' => $this->profile?->given_name,
            'family_name' => $this->profile?->family_name,
            'church_id' => $membership?->church?->public_id,
            'church_name' => $membership?->church?->name,
            'roles' => $roles,
            'type' => implode(' · ', $roles),
            'status' => $status,
            'archived_at' => $this->archived_at?->utc()->toIso8601String(),
            'last_contact' => $membership?->joined_at?->utc()->toIso8601String() ?? $this->updated_at?->utc()->toIso8601String(),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function safeguardingIncident(): array
    {
        return [
            'id' => $this->public_id,
            'reference_code' => $this->reference_code,
            'concern_type' => $this->concern_type,
            'name' => $this->reference_code ?: $this->concern_type,
            'type' => $this->concern_type,
            'severity' => $this->severity?->value ?? $this->severity,
            'status' => $this->status?->value ?? $this->status,
            'assigned_to_user_id' => $this->assignedTo?->public_id,
            'assigned_to_name' => $this->assignedTo?->name,
            'reported_by_user_id' => $this->reportedBy?->public_id,
            'reported_by_name' => $this->reportedBy?->name,
            'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
            'reported_at' => $this->reported_at?->utc()->toIso8601String(),
            'closed_at' => $this->closed_at?->utc()->toIso8601String(),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }
}

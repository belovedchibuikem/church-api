<?php

namespace App\Safeguarding\Actions;

use App\Models\SafeguardingIncident;
use App\Models\User;
use App\Safeguarding\IncidentSeverity;
use App\Safeguarding\IncidentStatus;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class UpdateSafeguardingIncidentAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{assigned_to_user_id?: ?string, severity?: ?IncidentSeverity, close?: bool, note?: ?string}  $changes
     */
    public function handle(SafeguardingIncident $incident, array $changes, User $actor): SafeguardingIncident
    {
        return DB::transaction(function () use ($incident, $changes, $actor): SafeguardingIncident {
            $locked = SafeguardingIncident::query()->lockForUpdate()->findOrFail($incident->getKey());

            $audited = [];

            if (array_key_exists('assigned_to_user_id', $changes)) {
                $assigneeId = $changes['assigned_to_user_id'];
                if ($assigneeId === null || $assigneeId === '') {
                    $locked->assignedTo()->dissociate();
                    $audited['assigned_to_user_id'] = null;
                } else {
                    $assignee = User::query()->where('public_id', $assigneeId)->firstOrFail();
                    $locked->assignedTo()->associate($assignee);
                    $audited['assigned_to_user_id'] = $assignee->public_id;
                }
            }

            if (isset($changes['severity']) && $changes['severity'] instanceof IncidentSeverity) {
                $locked->severity = $changes['severity'];
                $audited['severity'] = $changes['severity']->value;
            }

            if (($changes['close'] ?? false) === true) {
                if ($locked->status === IncidentStatus::Closed) {
                    throw new InvalidArgumentException('This incident is already closed.');
                }
                $locked->status = IncidentStatus::Closed;
                $locked->closed_at = now()->utc();
                $audited['status'] = IncidentStatus::Closed->value;
            }

            $note = isset($changes['note']) ? trim((string) $changes['note']) : '';
            if ($note !== '') {
                $notes = is_array($locked->case_notes) ? $locked->case_notes : [];
                $notes[] = [
                    'id' => (string) Str::ulid(),
                    'at' => now()->utc()->toIso8601String(),
                    'author_user_id' => $actor->public_id,
                    'body' => $note,
                ];
                $locked->case_notes = $notes;
                $audited['note_added'] = true;
            }

            if ($audited === []) {
                throw new InvalidArgumentException('No incident workflow change was provided.');
            }

            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'safeguarding.incident.updated',
                actor: $actor,
                targetType: 'safeguarding_incident',
                targetId: $locked->public_id,
                metadata: $audited,
            ));

            return $locked;
        }, attempts: 3);
    }
}

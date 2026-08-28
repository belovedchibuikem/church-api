<?php

namespace App\Safeguarding\Actions;

use App\Models\Person;
use App\Models\SafeguardingIncident;
use App\Models\User;
use App\Safeguarding\IncidentSeverity;
use App\Safeguarding\IncidentStatus;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ReportSafeguardingIncidentAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        string $concernType,
        IncidentSeverity $severity,
        string $restrictedSummary,
        ?Person $subject = null,
        ?User $reporter = null,
        ?\DateTimeInterface $occurredAt = null,
    ): SafeguardingIncident {
        if (! Str::isMatch('/\A[a-z][a-z0-9._-]{1,99}\z/', $concernType)) {
            throw new InvalidArgumentException('Concern type must be a stable code.');
        }

        if (trim($restrictedSummary) === '') {
            throw new InvalidArgumentException('A restricted incident summary is required.');
        }

        return DB::transaction(function () use ($concernType, $severity, $restrictedSummary, $subject, $reporter, $occurredAt): SafeguardingIncident {
            $incident = new SafeguardingIncident([
                'concern_type' => $concernType,
                'severity' => $severity,
                'restricted_summary' => $restrictedSummary,
                'occurred_at' => $occurredAt,
            ]);
            $incident->reference_code = 'SG-'.Str::upper(Str::random(16));
            $incident->status = IncidentStatus::Reported;
            $incident->reported_at = now()->utc();
            $incident->subject()->associate($subject);
            $incident->reportedBy()->associate($reporter);
            $incident->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'safeguarding.incident.reported',
                actor: $reporter,
                targetType: 'safeguarding_incident',
                targetId: $incident->public_id,
                metadata: [
                    'severity' => $severity->value,
                    'status' => $incident->status->value,
                ],
            ));

            return $incident;
        }, attempts: 3);
    }
}

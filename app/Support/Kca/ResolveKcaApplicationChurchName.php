<?php

namespace App\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Models\Church;
use App\Models\HomeChurch;
use App\Models\KcaApplication;
use App\Models\KcaGovernanceConfiguration;

final class ResolveKcaApplicationChurchName
{
    /** @param  array<string, mixed>|null  $applicationData */
    public function fromApplicationData(?array $applicationData): ?string
    {
        if ($applicationData === null) {
            return null;
        }

        $direct = data_get($applicationData, 'church_name')
            ?? data_get($applicationData, 'church')
            ?? data_get($applicationData, 'home_church')
            ?? data_get($applicationData, 'home_church_name');

        if (is_string($direct) && trim($direct) !== '') {
            return trim($direct);
        }

        $churchId = data_get($applicationData, 'church_id');
        if (is_string($churchId) && trim($churchId) !== '') {
            $name = Church::query()->where('public_id', trim($churchId))->value('name');
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        $homeChurchId = data_get($applicationData, 'home_church_id');
        if (is_string($homeChurchId) && trim($homeChurchId) !== '') {
            $homeChurch = HomeChurch::query()
                ->with('church:id,name,public_id')
                ->where('public_id', trim($homeChurchId))
                ->first();

            if ($homeChurch !== null) {
                return $homeChurch->name ?: $homeChurch->church?->name;
            }
        }

        return null;
    }

    public function batchLabel(KcaApplication $application): ?string
    {
        $fromData = data_get($application->application_data, 'batch_name')
            ?? data_get($application->application_data, 'batch')
            ?? data_get($application->application_data, 'cohort_name')
            ?? data_get($application->application_data, 'year_name');

        if (is_string($fromData) && trim($fromData) !== '') {
            return trim($fromData);
        }

        if ($application->relationLoaded('enrollment')) {
            $cohortName = $application->enrollment?->cohort?->name;
            if (is_string($cohortName) && $cohortName !== '') {
                return $cohortName;
            }
        }

        return null;
    }

    public function defaultLetterBody(
        string $applicantName,
        ?string $churchName,
        ?string $batchLabel,
    ): string {
        $batch = $batchLabel ?: 'the upcoming intake';
        $church = $churchName ?: 'your church';

        return implode("\n\n", [
            "We are pleased to inform you that you have been accepted into the Kingdom Change Agents programme ({$batch}).",
            "Your admission reflects our confidence in your potential, character, and commitment to Christian leadership through {$church}.",
            'Please attend the orientation program, complete registration, and prepare for the training journey ahead.',
            'Congratulations and welcome to KCA.',
        ]);
    }

    public function governanceDefaults(): KcaGovernanceConfiguration
    {
        return KcaGovernanceConfiguration::query()->first() ?? new KcaGovernanceConfiguration([
            'admission_signer_name' => 'Provost, KCA',
            'admission_signer_title' => 'Kingdom Change Agents',
        ]);
    }

    public function canIssueFor(KcaApplication $application): bool
    {
        $status = $application->status instanceof KcaApplicationState
            ? $application->status
            : KcaApplicationState::from((string) $application->status);

        return $status->permitsEnrollment();
    }
}

<?php

namespace App\Reporting;

use InvalidArgumentException;

final class MetricDefinitionRegistry
{
    /**
     * @return list<MetricDefinition>
     */
    public function all(): array
    {
        return array_map(
            fn (MetricKey $key): MetricDefinition => $this->definitionFor($key),
            MetricKey::cases(),
        );
    }

    public function get(MetricKey|string $key): MetricDefinition
    {
        $metric = is_string($key) ? MetricKey::tryFrom($key) : $key;

        if ($metric === null) {
            throw new InvalidArgumentException('Unknown canonical metric key.');
        }

        return $this->definitionFor($metric);
    }

    private function definitionFor(MetricKey $key): MetricDefinition
    {
        return match ($key) {
            MetricKey::GlobalCountries => $this->count($key, 'Countries', 'Active country records.'),
            MetricKey::GlobalChurches => $this->count($key, 'Churches', 'Canonical church records.'),
            MetricKey::GlobalHomeChurches => $this->count($key, 'Home Churches', 'Canonical Home Church records.'),
            MetricKey::GlobalMembers => $this->count($key, 'Members', 'Active church memberships.', true),
            MetricKey::GlobalKcaStudents => $this->count($key, 'KCA students', 'Active KCA enrolments.', true),
            MetricKey::GlobalKcaGraduates => $this->count($key, 'KCA graduates', 'Issued KCA certificates.', true),
            MetricKey::GlobalSoulsWon => $this->count($key, 'Souls won', 'Mission journeys classified as won.', true),
            MetricKey::GlobalMissionProjects => $this->count($key, 'Mission projects', 'Canonical crusade records.'),

            MetricKey::ChurchMembershipGrowth => $this->periodDelta($key, 'Membership growth', 'Net active membership change.', true),
            MetricKey::ChurchAttendance => $this->periodCount($key, 'Attendance', 'Recorded church attendance.', true),
            MetricKey::ChurchFirstTimerConversion => $this->ratio($key, 'First-timer conversion', 'First timers who become active members.', true),
            MetricKey::ChurchRetention => $this->ratio($key, 'Retention', 'Members retained across the reporting window.', true),
            MetricKey::ChurchEvangelism => $this->periodCount($key, 'Evangelism', 'Evangelism outcomes recorded by a church.', true),
            MetricKey::ChurchHomeChurchGrowth => $this->periodDelta($key, 'Home Church growth', 'Net active Home Church change.'),

            MetricKey::MissionSoulsReached => $this->periodCount($key, 'Souls reached', 'Mission soul journeys captured.', true),
            MetricKey::MissionSoulsWon => $this->periodCount($key, 'Souls won', 'Mission journeys classified as won.', true),
            MetricKey::MissionFollowUpCompletion => $this->ratio($key, 'Follow-up completion', 'Completed follow-ups over due follow-ups.', true),
            MetricKey::MissionChurchConnection => $this->ratio($key, 'Church connection', 'Souls connected to a canonical church.', true),
            MetricKey::MissionCrusades => $this->periodCount($key, 'Crusades', 'Crusades in the reporting window.'),
            MetricKey::MissionCountriesReached => $this->count($key, 'Countries reached', 'Distinct mission countries.'),

            MetricKey::KcaEnrollment => $this->periodCount($key, 'Enrolment', 'KCA enrolments in the reporting window.', true),
            MetricKey::KcaCompletion => $this->ratio($key, 'Completion', 'Completed enrolments over eligible enrolments.', true),
            MetricKey::KcaModulePerformance => $this->aggregate($key, 'Module performance', 'Approved assessment results by module.', true),
            MetricKey::KcaMentorEffectiveness => $this->aggregate($key, 'Mentor effectiveness', 'Governed mentor outcome aggregate.', true),
            MetricKey::KcaGraduates => $this->periodCount($key, 'Graduates', 'Certificates issued in the reporting window.', true),
            MetricKey::KcaActiveChangeAgents => $this->count($key, 'Active Change Agents', 'Active certified KCA graduates.', true),

            MetricKey::PressPublications => $this->count($key, 'Publications', 'Published canonical Press works.'),
            MetricKey::PressDownloads => $this->periodCount($key, 'Downloads', 'Privacy-safe publication download events.'),
            MetricKey::PressSales => new MetricDefinition($key, 'Sales', 'Sales from reconciled payment transactions only.', 'reconciled_sum'),
            MetricKey::PressLanguages => $this->count($key, 'Languages', 'Distinct published translation languages.'),
            MetricKey::PressReaders => $this->aggregate($key, 'Readers', 'Privacy-safe distinct readership estimate.'),
        };
    }

    private function count(MetricKey $key, string $label, string $description, bool $personal = false): MetricDefinition
    {
        return new MetricDefinition($key, $label, $description, 'current_count', $personal);
    }

    private function periodCount(MetricKey $key, string $label, string $description, bool $personal = false): MetricDefinition
    {
        return new MetricDefinition($key, $label, $description, 'period_count', $personal);
    }

    private function periodDelta(MetricKey $key, string $label, string $description, bool $personal = false): MetricDefinition
    {
        return new MetricDefinition($key, $label, $description, 'period_delta', $personal);
    }

    private function ratio(MetricKey $key, string $label, string $description, bool $personal = false): MetricDefinition
    {
        return new MetricDefinition($key, $label, $description, 'governed_ratio', $personal);
    }

    private function aggregate(MetricKey $key, string $label, string $description, bool $personal = false): MetricDefinition
    {
        return new MetricDefinition($key, $label, $description, 'governed_aggregate', $personal);
    }
}

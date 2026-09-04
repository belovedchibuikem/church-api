<?php

namespace App\Queries\Admin;

use App\Admin\AdminDashboardModule;
use App\Admin\DashboardPeriod;
use App\Church\ChurchMembershipStatus;
use App\Church\HomeChurchApplicationStatus;
use App\Kca\KcaApplicationState;
use App\Mission\MissionSoulJourneyStatus;
use App\Models\AccessDecision;
use App\Models\AdministrativeUnit;
use App\Models\AuditEvent;
use App\Models\Church;
use App\Models\ChurchDepartment;
use App\Models\ChurchGroup;
use App\Models\ChurchMembership;
use App\Models\ChurchRoleAssignment;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\Convert;
use App\Models\Crusade;
use App\Models\EvangelismActivity;
use App\Models\EventAttendance;
use App\Models\FirstTimer;
use App\Models\HomeChurch;
use App\Models\HomeChurchApplication;
use App\Models\HomeChurchAttendanceRecord;
use App\Models\KcaApplication;
use App\Models\KcaEnrollment;
use App\Models\MissionSoulJourney;
use App\Models\PastoralNeed;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Person;
use App\Models\PressPublication;
use App\Models\PressTranslation;
use App\Models\SafeguardingIncident;
use App\Models\User;
use App\Press\PressPublicationStatus;
use App\Safeguarding\IncidentSeverity;
use App\Safeguarding\IncidentStatus;
use App\Support\Authorization\ScopeReference;
use App\Support\Communication\CommunicationCopy;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardQuery
{
    public function summarize(
        AdminDashboardModule $module,
        ScopeReference $scope,
        DashboardPeriod $period,
        string $currency = 'NGN',
    ): array {
        $payload = match ($module) {
            AdminDashboardModule::Global => $this->global($scope, $period),
            AdminDashboardModule::Geography => $this->geography($scope, $period),
            AdminDashboardModule::HomeChurches => $this->homeChurches($scope, $period),
            AdminDashboardModule::Church => $this->church($scope, $period, $currency),
            AdminDashboardModule::People => $this->people($scope, $period),
            AdminDashboardModule::Kca => $this->kca($scope, $period),
            AdminDashboardModule::Mission => $this->mission($scope, $period),
            AdminDashboardModule::Press => $this->press($scope, $period),
            AdminDashboardModule::Finance => $this->finance($scope, $period, $currency),
            AdminDashboardModule::Communications => $this->communications($scope, $period),
            AdminDashboardModule::Reports => $this->reports($scope, $period),
            AdminDashboardModule::Security => $this->security($scope, $period),
            AdminDashboardModule::Safeguarding => $this->safeguarding($scope, $period),
        };

        return [
            ...$payload,
            'period' => $period->toArray(),
            'currency' => $currency,
            'scope' => [
                'type' => $scope->type,
                'id' => $scope->key,
            ],
        ];
    }

    private function global(ScopeReference $scope, DashboardPeriod $period): array
    {
        $churchQuery = $this->churchQuery($scope);
        $homeChurchQuery = $this->homeChurchQuery($scope);
        $membershipQuery = $this->membershipQuery($scope);
        $countriesWithChurches = $this->churchUnitsQuery($scope)
            ->join('countries', 'countries.id', '=', 'administrative_units.country_id');

        return [
            'metrics' => [
                $this->metric('Total Churches', (clone $churchQuery)->count(), (clone $churchQuery), 'created_at', period: $period),
                $this->metric('Home Churches', (clone $homeChurchQuery)->count(), (clone $homeChurchQuery), 'created_at', period: $period),
                $this->metric('Members', (clone $membershipQuery)->count(), (clone $membershipQuery), 'joined_at', period: $period),
                $this->presenceMetric('Countries', $countriesWithChurches, 'countries.id', $period),
            ],
            'breakdown' => $this->topCountriesByChurches($scope, 5),
            'series' => $this->monthlySeries((clone $churchQuery), 'created_at', $period),
            'recent_activities' => $this->recentAuditActivities($scope, 4),
        ];
    }

    private function geography(ScopeReference $scope, DashboardPeriod $period): array
    {
        $churchQuery = $this->churchQuery($scope);
        $units = $this->churchUnitsQuery($scope);
        $countriesWithChurches = (clone $units)
            ->join('countries', 'countries.id', '=', 'administrative_units.country_id');
        $localAreasWithChurches = (clone $units)
            ->whereNotNull('administrative_units.parent_id');

        return [
            'metrics' => [
                $this->presenceMetric('Countries', $countriesWithChurches, 'countries.id', $period),
                $this->presenceMetric(
                    'Regions / States',
                    $units,
                    'COALESCE(administrative_units.parent_id, administrative_units.id)',
                    $period,
                ),
                $this->presenceMetric('Local Areas', $localAreasWithChurches, 'administrative_units.id', $period),
                $this->metric('Churches', (clone $churchQuery)->count(), (clone $churchQuery), 'created_at', period: $period),
            ],
            'breakdown' => $this->topCountriesByChurches($scope, 5),
            'series' => $this->monthlySeries((clone $churchQuery), 'created_at', $period),
            'recent_activities' => $this->recentAuditActivities($scope, 4),
        ];
    }

    private function homeChurches(ScopeReference $scope, DashboardPeriod $period): array
    {
        $homeChurchQuery = $this->homeChurchQuery($scope);
        $applicationQuery = $this->homeChurchApplicationQuery($scope);
        $membershipQuery = $this->membershipQuery($scope);
        $activeThisWeek = (clone $homeChurchQuery)
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        $statusBreakdown = (clone $applicationQuery)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $breakdown = $this->statusBreakdownItems($statusBreakdown);
        $churchIds = $this->churchIds($scope);
        $attendanceQuery = HomeChurchAttendanceRecord::query();
        $needQuery = PastoralNeed::query()->where('status', 'open');
        $activityQuery = EvangelismActivity::query();
        if ($churchIds !== null) {
            $attendanceQuery->whereHas('homeChurch', fn ($query) => $query->whereIn('church_id', $churchIds));
            $needQuery->whereHas('person.memberships', fn ($query) => $query->whereIn('church_id', $churchIds)->whereNotNull('home_church_id'));
            $activityQuery->whereIn('church_id', $churchIds);
        }

        return [
            'metrics' => [
                $this->metric('Total Home Churches', (clone $homeChurchQuery)->count(), (clone $homeChurchQuery), 'created_at', period: $period),
                $this->metric('Active Home Churches', (clone $homeChurchQuery)->where('status', 'active')->count(), (clone $homeChurchQuery)->where('status', 'active'), 'created_at', period: $period),
                $this->metric('Total Members', (clone $membershipQuery)->whereNotNull('home_church_id')->count(), (clone $membershipQuery)->whereNotNull('home_church_id'), 'joined_at', period: $period),
                $this->metric('Leaders', (clone $homeChurchQuery)->whereNotNull('leader_person_id')->count()),
                $this->metric('New Applications', (clone $applicationQuery)->whereIn('status', [
                    HomeChurchApplicationStatus::Submitted->value,
                    HomeChurchApplicationStatus::UnderReview->value,
                    HomeChurchApplicationStatus::InformationRequired->value,
                    HomeChurchApplicationStatus::InterviewOrientation->value,
                    HomeChurchApplicationStatus::Deferred->value,
                ])->count(), (clone $applicationQuery), 'created_at', period: $period),
                $this->metric('Attendance Sessions', (clone $attendanceQuery)->count(), (clone $attendanceQuery), 'service_date', period: $period),
                $this->metric('Activities', (clone $activityQuery)->count(), (clone $activityQuery), 'occurred_at', period: $period),
                $this->metric('Open Needs', (clone $needQuery)->count(), (clone $needQuery), 'created_at', period: $period),
                $this->metric('Active This Week', $activeThisWeek),
            ],
            'breakdown' => $breakdown,
            'donut' => [
                'value' => $this->formatNumber((int) $statusBreakdown->sum()),
                'label' => 'Total',
            ],
            'series' => $this->monthlySeries((clone $homeChurchQuery), 'created_at', $period),
            'recent_activities' => $this->recentAuditActivities($scope, 4, [
                'home_church.%',
                'church.home_church%',
            ]),
        ];
    }

    private function church(ScopeReference $scope, DashboardPeriod $period, string $currency = 'NGN'): array
    {
        $churchQuery = $this->churchQuery($scope);
        $membershipQuery = $this->membershipQuery($scope);
        $firstTimerQuery = $this->firstTimerQuery($scope);
        $churchIds = $this->churchIds($scope);
        $convertQuery = Convert::query();
        $leaderQuery = ChurchRoleAssignment::query()->where('role_type', 'leader')->where('status', 'active')->whereNull('ended_at');
        $workerQuery = ChurchRoleAssignment::query()->where('role_type', 'worker')->where('status', 'active')->whereNull('ended_at');
        $discipleQuery = ChurchRoleAssignment::query()->where('role_type', 'disciple')->where('status', 'active')->whereNull('ended_at');
        $departmentQuery = ChurchDepartment::query();
        $groupQuery = ChurchGroup::query();
        $evangelismQuery = EvangelismActivity::query();
        if ($churchIds !== null) {
            $convertQuery->whereIn('church_id', $churchIds);
            $leaderQuery->whereIn('church_id', $churchIds);
            $workerQuery->whereIn('church_id', $churchIds);
            $discipleQuery->whereIn('church_id', $churchIds);
            $departmentQuery->whereIn('church_id', $churchIds);
            $groupQuery->whereIn('church_id', $churchIds);
            $evangelismQuery->whereIn('church_id', $churchIds);
        }
        $attendanceCount = $this->scopedEventAttendanceCount($scope);
        $withHomeChurch = (clone $membershipQuery)->whereNotNull('home_church_id')->count();
        $withoutHomeChurch = (clone $membershipQuery)->whereNull('home_church_id')->count();
        $totalMembers = max(1, (clone $membershipQuery)->count());
        $givingQuery = PaymentTransaction::query();
        $this->applyPayerChurchScope($givingQuery, $scope);
        $this->constrainToPeriod($givingQuery, 'occurred_at', $period);
        $givingMinor = (int) (clone $givingQuery)->sum('amount_minor');

        return [
            'metrics' => [
                $this->metric('Churches', (clone $churchQuery)->count(), (clone $churchQuery), 'created_at', period: $period),
                $this->metric('Total Members', (clone $membershipQuery)->count(), (clone $membershipQuery), 'joined_at', period: $period),
                $this->metric('First Timers', (clone $firstTimerQuery)->count(), (clone $firstTimerQuery), 'registered_at', period: $period),
                $this->metric('Converts', (clone $convertQuery)->count(), (clone $convertQuery), 'converted_at', period: $period),
                $this->metric('Leaders', (clone $leaderQuery)->count()),
                $this->metric('Disciples', (clone $discipleQuery)->count()),
                $this->metric('Workers', (clone $workerQuery)->count()),
                $this->metric('Departments', (clone $departmentQuery)->count(), (clone $departmentQuery), 'created_at', period: $period),
                $this->metric('Small Groups', (clone $groupQuery)->count(), (clone $groupQuery), 'created_at', period: $period),
                $this->metric('Evangelism', (clone $evangelismQuery)->count(), (clone $evangelismQuery), 'occurred_at', period: $period),
                $this->metric('Attendance (Avg)', $attendanceCount),
                $this->metric('Giving', $this->formatCurrency($givingMinor, $currency)),
            ],
            'breakdown' => $this->percentBreakdown([
                ['With Home Church', $withHomeChurch],
                ['Church Only', $withoutHomeChurch],
                ['First Timers', (clone $firstTimerQuery)->count()],
            ], $totalMembers),
            'donut' => [
                'value' => $this->formatNumber($attendanceCount > 0 ? $attendanceCount : (clone $membershipQuery)->count()),
                'label' => $attendanceCount > 0 ? 'Average' : 'Members',
            ],
            'series' => $this->monthlySeries((clone $membershipQuery), 'joined_at', $period),
            'recent_activities' => $this->recentChurchActivities($scope, 4),
        ];
    }

    private function people(ScopeReference $scope, DashboardPeriod $period): array
    {
        $personQuery = Person::query()->whereNull('archived_at');
        $churchIds = $this->churchIds($scope);
        if ($churchIds !== null) {
            $personQuery->where(function (Builder $inner) use ($churchIds): void {
                $inner->whereHas('memberships', fn (Builder $m) => $m->whereIn('church_id', $churchIds))
                    ->orWhereHas('firstTimers', fn (Builder $f) => $f->whereIn('church_id', $churchIds))
                    ->orWhereHas('converts', fn (Builder $c) => $c->whereIn('church_id', $churchIds))
                    ->orWhereHas('roleAssignments', fn (Builder $r) => $r->whereIn('church_id', $churchIds));
            });
        }
        $activeQuery = (clone $personQuery)->where(function (Builder $inner): void {
            $inner->whereHas('memberships', fn (Builder $m) => $m->where('status', ChurchMembershipStatus::Active->value)->whereNull('ended_at'))
                ->orWhereHas('user', fn (Builder $user) => $user->where('account_status', 'active'));
        });
        $convertQuery = Convert::query()->whereHas('person', fn (Builder $person) => $person->whereNull('archived_at'));
        if ($churchIds !== null) {
            $convertQuery->whereIn('church_id', $churchIds);
        }

        $total = (clone $personQuery)->count();
        $active = (clone $activeQuery)->count();
        $newThisPeriod = (clone $personQuery)->whereBetween('created_at', [$period->from, $period->to])->count();
        $converted = (clone $convertQuery)->distinct()->count('person_id');

        return [
            'metrics' => [
                $this->metric('Total People', $total, (clone $personQuery), 'created_at', period: $period, hint: 'Distinct canonical people in this scope who are not archived.'),
                $this->metric('Active', $active, hint: 'People with an active open membership or an active user account. Overlaps Total.'),
                $this->metric('New This Month', $newThisPeriod, (clone $personQuery), 'created_at', period: $period, hint: 'People whose canonical record was created in the selected period. Not a remainder of Total minus Active.'),
                $this->metric('Converted', $converted, (clone $convertQuery), 'converted_at', period: $period, hint: 'Distinct people with a conversion record in this scope. Overlaps Total; not added to Active or New.'),
            ],
            'breakdown' => $this->percentBreakdown([
                ['Active', $active],
                ['Converted', $converted],
                ['New this period', $newThisPeriod],
            ], max(1, $total)),
            'series' => $this->monthlySeries((clone $personQuery), 'created_at', $period),
            'donut' => [
                'value' => $this->formatNumber($total),
                'label' => 'People',
            ],
            'definitions' => [
                'Total People is the count of distinct non-archived people in the current church/global scope.',
                'Active, New This Month and Converted are overlapping subsets — they must not be added together to equal Total.',
            ],
            'recent_activities' => $this->recentAuditActivities($scope, 4, ['people.%', 'church.first_timer%', 'church.membership%']),
        ];
    }

    private function kca(ScopeReference $scope, DashboardPeriod $period): array
    {
        $applicationQuery = KcaApplication::query();
        $this->applyPersonChurchScope($applicationQuery, $scope);
        $enrollmentQuery = KcaEnrollment::query();
        $this->applyPersonChurchScope($enrollmentQuery, $scope);
        $statusBreakdown = (clone $applicationQuery)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $recent = (clone $applicationQuery)
            ->with([...PersonDisplayName::eager(), 'person.memberships.church:id,public_id,name'])
            ->latest('received_at')
            ->limit(5)
            ->get()
            ->map(fn (KcaApplication $application): array => [
                'Applicant' => PersonDisplayName::of($application->person) ?: 'Applicant',
                'Church' => $application->person?->memberships->first()?->church?->name ?? '—',
                'Submitted' => $application->received_at?->format('M j, Y') ?? '—',
                'Status' => $application->status instanceof KcaApplicationState
                  ? ucwords(str_replace('_', ' ', $application->status->value))
                  : (string) $application->status,
            ])
            ->all();

        return [
            'metrics' => [
                $this->metric('Applications', (clone $applicationQuery)->count(), (clone $applicationQuery), 'received_at', period: $period),
                $this->metric('Under Review', (clone $applicationQuery)->where('status', KcaApplicationState::Reviewed->value)->count()),
                $this->metric('Accepted', (clone $applicationQuery)->whereIn('status', [
                    KcaApplicationState::Accepted->value,
                    KcaApplicationState::ProvisionallyAccepted->value,
                ])->count()),
                $this->metric('Enrollments', (clone $enrollmentQuery)->count(), (clone $enrollmentQuery), 'starts_on', period: $period),
            ],
            'breakdown' => $this->statusBreakdownItems($statusBreakdown),
            'donut' => [
                'value' => $this->formatNumber((clone $applicationQuery)->count()),
                'label' => 'Applications',
            ],
            'series' => $this->monthlySeries((clone $applicationQuery), 'received_at', $period),
            'recent_rows' => $recent,
        ];
    }

    private function mission(ScopeReference $scope, DashboardPeriod $period): array
    {
        $crusadeQuery = $this->crusadeQuery($scope);
        $soulQuery = $this->soulQuery($scope);
        $won = (clone $soulQuery)->whereNotNull('converted_at')->count();
        $statusBreakdown = (clone $soulQuery)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $topCrusades = (clone $crusadeQuery)
            ->withCount('soulJourneys')
            ->orderByDesc('soul_journeys_count')
            ->limit(5)
            ->get()
            ->map(fn (Crusade $crusade): array => [
                'Crusade Name' => $crusade->name,
                'Souls' => $this->formatNumber((int) $crusade->soul_journeys_count),
            ])
            ->all();

        return [
            'metrics' => [
                $this->metric('Total Crusades', (clone $crusadeQuery)->count(), (clone $crusadeQuery), 'starts_at', period: $period),
                $this->metric('Souls Captured', (clone $soulQuery)->count(), (clone $soulQuery), 'captured_at', period: $period),
                $this->metric('Souls Won', $won, (clone $soulQuery)->whereNotNull('converted_at'), 'converted_at', period: $period),
                $this->metric('Active Follow-ups', (clone $soulQuery)->whereIn('status', [
                    MissionSoulJourneyStatus::MentorAssigned->value,
                    MissionSoulJourneyStatus::FollowUpActive->value,
                ])->count()),
            ],
            'breakdown' => $this->statusBreakdownItems($statusBreakdown),
            'donut' => [
                'value' => $this->formatNumber((clone $soulQuery)->count()),
                'label' => 'Total',
            ],
            'series' => $this->monthlySeries((clone $soulQuery), 'captured_at', $period),
            'recent_rows' => $topCrusades,
        ];
    }

    private function press(ScopeReference $scope, DashboardPeriod $period): array
    {
        unset($scope);
        $publicationQuery = PressPublication::query();
        $translationQuery = PressTranslation::query();
        $published = (clone $publicationQuery)->whereIn('status', [
            PressPublicationStatus::Published->value,
            PressPublicationStatus::Distribution->value,
        ])->whereNull('archived_at')->count();
        $inProduction = (clone $publicationQuery)->whereNotIn('status', [
            PressPublicationStatus::Published->value,
            PressPublicationStatus::Distribution->value,
            PressPublicationStatus::Manuscript->value,
            PressPublicationStatus::Draft->value,
            PressPublicationStatus::Archived->value,
            PressPublicationStatus::Unpublished->value,
            PressPublicationStatus::Rejected->value,
        ])->count();

        $topPublications = (clone $publicationQuery)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (PressPublication $publication): array => $this->breakdownItem(
                $publication->title,
                ucwords(str_replace('_', ' ', $publication->status instanceof PressPublicationStatus
                  ? $publication->status->value
                  : (string) $publication->status)),
            ))
            ->all();

        return [
            'metrics' => [
                $this->metric('Publications', (clone $publicationQuery)->count(), (clone $publicationQuery), 'updated_at', period: $period),
                $this->metric('Manuscripts', (clone $publicationQuery)->whereIn('status', [
                    PressPublicationStatus::Manuscript->value,
                    PressPublicationStatus::Draft->value,
                ])->count()),
                $this->metric('In Production', $inProduction),
                $this->metric('Published', $published, (clone $publicationQuery)->where('status', PressPublicationStatus::Published->value), 'updated_at', period: $period),
            ],
            'breakdown' => $topPublications,
            'series' => $this->monthlySeries((clone $publicationQuery), 'updated_at', $period),
        ];
    }

    private function finance(ScopeReference $scope, DashboardPeriod $period, string $currency): array
    {
        $transactionQuery = PaymentTransaction::query();
        $this->applyPayerChurchScope($transactionQuery, $scope);
        $this->constrainToPeriod($transactionQuery, 'occurred_at', $period);
        $intentQuery = PaymentIntent::query();
        $this->applyPayerChurchScope($intentQuery, $scope);
        $this->constrainToPeriod($intentQuery, 'created_at', $period);
        $totalMinor = (int) (clone $transactionQuery)->sum('amount_minor');
        $donorCount = (int) (clone $intentQuery)->distinct('payer_person_id')->count('payer_person_id');
        $transactionCount = (clone $transactionQuery)->count();
        $averageMinor = $transactionCount > 0 ? (int) round($totalMinor / $transactionCount) : 0;

        $breakdown = (clone $intentQuery)
            ->selectRaw('purpose_code, COUNT(*) as total, SUM(amount_minor) as amount_minor')
            ->groupBy('purpose_code')
            ->orderByDesc('amount_minor')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => $this->breakdownItem(
                ucwords(str_replace('_', ' ', (string) $row->purpose_code)),
                $this->formatCurrency((int) $row->amount_minor, $currency),
            ))
            ->all();

        $unscopedTransactions = PaymentTransaction::query();
        $this->applyPayerChurchScope($unscopedTransactions, $scope);

        return [
            'metrics' => [
                $this->metric('Total Receipts', $this->formatCurrency($totalMinor, $currency), null, null, $totalMinor, (clone $unscopedTransactions), 'occurred_at', $period),
                $this->metric('Total Transactions', $transactionCount, (clone $unscopedTransactions), 'occurred_at', period: $period),
                $this->metric('Total Donors', $donorCount),
                $this->metric('Average Gift', $this->formatCurrency($averageMinor, $currency)),
            ],
            'breakdown' => $breakdown !== [] ? $breakdown : [
                $this->breakdownItem('Transactions', $this->formatNumber($transactionCount)),
                $this->breakdownItem('Receipts', $this->formatCurrency($totalMinor, $currency)),
                $this->breakdownItem('Donors', $this->formatNumber($donorCount)),
            ],
            'series' => $this->monthlySumSeries((clone $unscopedTransactions), 'occurred_at', 'amount_minor', $period),
        ];
    }

    private function communications(ScopeReference $scope, DashboardPeriod $period): array
    {
        unset($scope);
        $deliveryQuery = CommunicationDeliveryAttempt::query();
        $this->constrainToPeriod($deliveryQuery, 'created_at', $period);
        $sent = (clone $deliveryQuery)->count();
        $delivered = (clone $deliveryQuery)->where('status', 'succeeded')->count();
        $failed = (clone $deliveryQuery)->whereIn('status', ['failed', 'suppressed'])->count();
        $pending = (clone $deliveryQuery)->where('status', 'pending')->count();

        $channelCounts = (clone $deliveryQuery)
            ->selectRaw('channel, COUNT(*) as aggregate')
            ->groupBy('channel')
            ->pluck('aggregate', 'channel');

        if ($channelCounts->isEmpty()) {
            $channelCounts = CommunicationBroadcast::query()
                ->selectRaw('channel, COUNT(*) as aggregate')
                ->groupBy('channel')
                ->pluck('aggregate', 'channel');
        }

        $channelSegments = $channelCounts
            ->map(fn (int|string $count, string $channel): array => [
                CommunicationCopy::channelLabel($channel),
                (int) $count,
            ])
            ->values()
            ->all();

        $recent = CommunicationBroadcast::query()
            ->with(['template:id,subject,code'])
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn (CommunicationBroadcast $broadcast): array => [
                'Campaign' => CommunicationCopy::campaignTitle(
                    $broadcast->template?->subject,
                    (string) $broadcast->purpose,
                ),
                'Channel' => CommunicationCopy::channelLabel(
                    $broadcast->channel instanceof \BackedEnum ? $broadcast->channel->value : (string) $broadcast->channel,
                ),
                'Status' => ucfirst((string) ($broadcast->status instanceof \BackedEnum ? $broadcast->status->value : $broadcast->status)),
            ])
            ->all();

        return [
            'metrics' => [
                $this->metric('Messages Sent', $sent, CommunicationDeliveryAttempt::query(), 'created_at', period: $period),
                $this->metric('Delivered', $delivered, CommunicationDeliveryAttempt::query()->where('status', 'succeeded'), 'created_at', period: $period),
                $this->metric('Failed', $failed),
                $this->metric('Pending', $pending),
            ],
            'breakdown' => $this->percentBreakdown($channelSegments, max(1, (int) $channelCounts->sum())),
            'donut' => [
                'value' => $this->formatNumber($sent),
                'label' => 'Sent',
            ],
            'series' => $this->monthlySeries(CommunicationDeliveryAttempt::query(), 'created_at', $period),
            'recent_rows' => $recent,
        ];
    }

    private function reports(ScopeReference $scope, DashboardPeriod $period): array
    {
        $churchQuery = $this->churchQuery($scope);
        $homeChurchQuery = $this->homeChurchQuery($scope);
        $membershipQuery = $this->membershipQuery($scope);

        $countryCount = $this->distinctChurchGeographyCount(
            $this->churchUnitsQuery($scope)->join('countries', 'countries.id', '=', 'administrative_units.country_id'),
            'countries.id',
        );
        $peopleCount = (clone $membershipQuery)->count();
        $churchCount = (clone $churchQuery)->count();
        $homeChurchCount = (clone $homeChurchQuery)->count();
        $breakdown = $this->topCountriesByChurches($scope, 5);

        return [
            'metrics' => [
                $this->metric('Total People', $peopleCount, (clone $membershipQuery), 'joined_at', period: $period),
                $this->metric('Active Churches', $churchCount, (clone $churchQuery), 'created_at', period: $period),
                $this->metric('Home Churches', $homeChurchCount, (clone $homeChurchQuery), 'created_at', period: $period),
                $this->metric('Countries', $countryCount),
            ],
            'breakdown' => $breakdown,
            'donut' => [
                'value' => $this->formatNumber($peopleCount),
                'label' => 'People',
            ],
            'series' => $this->monthlySeries((clone $membershipQuery), 'joined_at', $period),
        ];
    }

    private function security(ScopeReference $scope, DashboardPeriod $period): array
    {
        $userQuery = User::query();
        $activeUsers = (clone $userQuery)->where('account_status', 'active')->count();
        $failedQuery = AccessDecision::query()->where('allowed', false);
        $this->applyAuthorizationScope($failedQuery, $scope);
        $this->constrainToPeriod($failedQuery, 'decided_at', $period);
        $failedLogins = (clone $failedQuery)->count();
        $alertQuery = AuditEvent::query()->where('action', 'like', 'security.%');
        $this->applyAuditScope($alertQuery, $scope);
        $this->constrainToPeriod($alertQuery, 'occurred_at', $period);
        $securityAlerts = (clone $alertQuery)->count();

        $recentAlerts = AuditEvent::query()
            ->where(function (Builder $query): void {
                $query->where('action', 'like', 'security.%')
                    ->orWhere('action', 'like', '%.denied');
            });
        $this->applyAuditScope($recentAlerts, $scope);
        $recentAlerts = $recentAlerts
            ->latest('occurred_at')
            ->limit(5)
            ->get()
            ->map(fn (AuditEvent $event): array => $this->breakdownItem(
                str_replace('.', ' ', $event->action),
                $event->occurred_at?->diffForHumans() ?? '—',
            ))
            ->all();

        $auditSeries = AuditEvent::query();
        $this->applyAuditScope($auditSeries, $scope);

        return [
            'metrics' => [
                $this->metric('User Accounts', (clone $userQuery)->count(), (clone $userQuery), 'created_at', period: $period),
                $this->metric('Active Users', $activeUsers),
                $this->metric('Failed Logins', $failedLogins, hint: 'Denied access decisions, not session rows'),
                $this->metric('Security Alerts', $securityAlerts),
            ],
            'breakdown' => $recentAlerts,
            'series' => $this->monthlySeries($auditSeries, 'occurred_at', $period),
            'definitions' => [
                'Failed Logins counts denied access decisions. Login History lists security sessions plus those denied decisions.',
            ],
        ];
    }

    private function safeguarding(ScopeReference $scope, DashboardPeriod $period): array
    {
        $openQuery = SafeguardingIncident::query()->where('status', '!=', IncidentStatus::Closed->value);
        $reviewQuery = SafeguardingIncident::query()->where('status', IncidentStatus::UnderReview->value);
        $highQuery = SafeguardingIncident::query()
            ->where('status', '!=', IncidentStatus::Closed->value)
            ->whereIn('severity', [IncidentSeverity::High->value, IncidentSeverity::Critical->value]);
        $closedQuery = SafeguardingIncident::query()->where('status', IncidentStatus::Closed->value);

        $concernBreakdown = SafeguardingIncident::query()
            ->select('concern_type', DB::raw('count(*) as aggregate'))
            ->groupBy('concern_type')
            ->pluck('aggregate', 'concern_type');

        $seriesQuery = SafeguardingIncident::query();

        return [
            'metrics' => [
                $this->metric('Open Cases', (clone $openQuery)->count(), $openQuery, 'reported_at', period: $period),
                $this->metric('Under Review', (clone $reviewQuery)->count(), $reviewQuery, 'reported_at', period: $period),
                $this->metric('High Priority', (clone $highQuery)->count(), $highQuery, 'reported_at', period: $period),
                $this->metric('Closed Cases', (clone $closedQuery)->count(), $closedQuery, 'closed_at', period: $period),
            ],
            'breakdown' => $this->statusBreakdownItems($concernBreakdown),
            'series' => $this->monthlySeries($seriesQuery, 'reported_at', $period),
            'recent_activities' => $this->recentAuditActivities($scope, 4, [
                'safeguarding.%',
            ]),
            'definitions' => [
                'Open Cases are incidents that are not closed. High Priority is high or critical severity that is still open.',
            ],
        ];
    }

    /** @return Builder<Church> */
    private function churchQuery(ScopeReference $scope): Builder
    {
        $query = Church::query();
        $churchIds = $this->churchIds($scope);

        if ($churchIds !== null) {
            $query->whereIn('id', $churchIds);
        }

        return $query;
    }

    /** @return Builder<HomeChurch> */
    private function homeChurchQuery(ScopeReference $scope): Builder
    {
        $query = HomeChurch::query();
        $churchIds = $this->churchIds($scope);

        if ($churchIds !== null) {
            $query->whereIn('church_id', $churchIds);
        }

        if ($scope->type === 'home_church') {
            $homeChurchId = HomeChurch::query()->where('public_id', $scope->key)->value('id');
            $query->where('id', $homeChurchId ?? 0);
        }

        return $query;
    }

    /** @return Builder<HomeChurchApplication> */
    private function homeChurchApplicationQuery(ScopeReference $scope): Builder
    {
        $query = HomeChurchApplication::query();
        $churchIds = $this->churchIds($scope);

        if ($churchIds !== null) {
            $query->whereIn('church_id', $churchIds);
        }

        if ($scope->type === 'home_church') {
            $homeChurchId = HomeChurch::query()->where('public_id', $scope->key)->value('id');
            $query->where('home_church_id', $homeChurchId ?? 0);
        }

        return $query;
    }

    /** @return Builder<ChurchMembership> */
    private function membershipQuery(ScopeReference $scope): Builder
    {
        $query = ChurchMembership::query()->where('status', ChurchMembershipStatus::Active->value);
        $churchIds = $this->churchIds($scope);

        if ($churchIds !== null) {
            $query->whereIn('church_id', $churchIds);
        }

        if ($scope->type === 'home_church') {
            $homeChurchId = HomeChurch::query()->where('public_id', $scope->key)->value('id');
            $query->where('home_church_id', $homeChurchId ?? 0);
        }

        return $query;
    }

    /** @return Builder<FirstTimer> */
    private function firstTimerQuery(ScopeReference $scope): Builder
    {
        $query = FirstTimer::query();
        $churchIds = $this->churchIds($scope);

        if ($churchIds !== null) {
            $query->whereIn('church_id', $churchIds);
        }

        if ($scope->type === 'home_church') {
            $homeChurchId = HomeChurch::query()->where('public_id', $scope->key)->value('id');
            $query->where('home_church_id', $homeChurchId ?? 0);
        }

        return $query;
    }

    /** @return Builder<Crusade> */
    private function crusadeQuery(ScopeReference $scope): Builder
    {
        $query = Crusade::query();
        $crusadeIds = $this->crusadeIds($scope);

        if ($crusadeIds !== null) {
            $query->whereIn('id', $crusadeIds);
        }

        return $query;
    }

    /** @return Builder<MissionSoulJourney> */
    private function soulQuery(ScopeReference $scope): Builder
    {
        $query = MissionSoulJourney::query();
        $crusadeIds = $this->crusadeIds($scope);

        if ($crusadeIds !== null) {
            $query->whereIn('crusade_id', $crusadeIds);
        }

        return $query;
    }

    private function scopedEventAttendanceCount(ScopeReference $scope): int
    {
        $churchIds = $this->churchIds($scope);

        if ($churchIds === []) {
            return 0;
        }

        $query = EventAttendance::query()
            ->where('attended_at', '>=', now()->subDays(30));

        if ($churchIds !== null) {
            $query->whereHas('registration.person.memberships', function (Builder $membershipQuery) use ($churchIds): void {
                $membershipQuery
                    ->whereIn('church_id', $churchIds)
                    ->where('status', ChurchMembershipStatus::Active->value);
            });
        }

        return $query->count();
    }

    /**
     * Distinct countries, states, or local areas that currently have churches.
     *
     * @param  Builder<Church>  $query
     * @return array{label: string, value: string, trend?: string}
     */
    private function presenceMetric(string $label, Builder $query, string $distinctExpression, DashboardPeriod $period): array
    {
        $metric = [
            'label' => $label,
            'value' => $this->formatNumber($this->distinctChurchGeographyCount($query, $distinctExpression)),
        ];

        $trend = $this->distinctPeriodTrend($query, $distinctExpression, 'churches.created_at', $period);
        if ($trend !== null) {
            $metric['trend'] = $trend;
        }

        return $metric;
    }

    /** @return array<int, array{label: string, value: string, trend?: string}> */
    private function metric(
        string $label,
        int|string $value,
        ?Builder $trendQuery = null,
        ?string $trendColumn = null,
        ?int $rawValue = null,
        ?Builder $alternateTrendQuery = null,
        ?string $alternateTrendColumn = null,
        ?DashboardPeriod $period = null,
        ?string $hint = null,
    ): array {
        $metric = [
            'label' => $label,
            'value' => is_int($value) ? $this->formatNumber($value) : (string) $value,
        ];
        if ($hint !== null) {
            $metric['hint'] = $hint;
        }

        $query = $alternateTrendQuery ?? $trendQuery;
        $column = $alternateTrendColumn ?? $trendColumn;

        if ($query !== null && $column !== null) {
            $trend = $this->periodTrend($query, $column, $period);
            if ($trend !== null) {
                $metric['trend'] = $trend;
            }
        }

        if ($rawValue !== null) {
            $metric['raw_value'] = $rawValue;
        }

        return $metric;
    }

    /** @return array{label: string, value: string, percent?: int} */
    private function breakdownItem(string $label, int|string $value, ?int $percent = null): array
    {
        $item = [
            'label' => $label,
            'value' => is_int($value) ? $this->formatNumber($value) : (string) $value,
        ];

        if ($percent !== null) {
            $item['percent'] = $percent;
        }

        return $item;
    }

    /** @param Collection<string, int|string> $counts */
    private function statusBreakdownItems(Collection $counts): array
    {
        $total = max(1, (int) $counts->sum());

        return $counts
            ->map(fn (int|string $count, string $status): array => $this->breakdownItem(
                ucwords(str_replace('_', ' ', $status)),
                (int) $count,
                (int) round(((int) $count / $total) * 100),
            ))
            ->values()
            ->all();
    }

    /** @param array<int, array{0: string, 1: int}> $segments */
    private function percentBreakdown(array $segments, int $total): array
    {
        return array_map(
            fn (array $segment): array => $this->breakdownItem(
                $segment[0],
                $segment[1],
                (int) round(($segment[1] / max(1, $total)) * 100),
            ),
            $segments,
        );
    }

    /** @return list<array{label: string, value: string, percent?: int}> */
    private function topCountriesByChurches(ScopeReference $scope, int $limit): array
    {
        $query = Church::query()
            ->select('countries.name', DB::raw('count(churches.id) as aggregate'))
            ->join('administrative_units', 'administrative_units.id', '=', 'churches.administrative_unit_id')
            ->join('countries', 'countries.id', '=', 'administrative_units.country_id')
            ->groupBy('countries.name')
            ->orderByDesc('aggregate')
            ->limit($limit);

        $churchIds = $this->churchIds($scope);
        if ($churchIds !== null) {
            $query->whereIn('churches.id', $churchIds);
        }

        $rows = $query->get();
        $total = max(1, (int) $rows->sum('aggregate'));

        return $rows->map(
            fn ($row): array => $this->breakdownItem(
                (string) $row->name,
                (int) $row->aggregate,
                (int) round(((int) $row->aggregate / $total) * 100),
            ),
        )->all();
    }

    /** @return Builder<Church> */
    private function churchUnitsQuery(ScopeReference $scope): Builder
    {
        $query = Church::query()
            ->join('administrative_units', 'administrative_units.id', '=', 'churches.administrative_unit_id');

        $churchIds = $this->churchIds($scope);
        if ($churchIds !== null) {
            $query->whereIn('churches.id', $churchIds);
        }

        return $query;
    }

    /** @param Builder<Church> $query */
    private function distinctChurchGeographyCount(Builder $query, string $expression): int
    {
        return (int) (clone $query)->distinct()->count(DB::raw($expression));
    }

    /** @return list<array{title: string, detail?: string, occurred_at: string}> */
    private function recentAuditActivities(ScopeReference $scope, int $limit, array $actionPatterns = []): array
    {
        $query = AuditEvent::query()->latest('occurred_at')->limit($limit);

        if ($actionPatterns !== []) {
            $query->where(function (Builder $builder) use ($actionPatterns): void {
                foreach ($actionPatterns as $pattern) {
                    $builder->orWhere('action', 'like', $pattern);
                }
            });
        }

        if ($scope->type !== 'global') {
            $query->where('scope_type', $scope->type)->where('scope_id', $scope->key);
        }

        return $query->get()->map(fn (AuditEvent $event): array => [
            'title' => str_replace(['.', '_'], ' ', $event->action),
            'detail' => $event->target_type,
            'occurred_at' => $event->occurred_at?->toIso8601String() ?? now()->toIso8601String(),
        ])->all();
    }

    /** @return list<array{title: string, detail?: string, occurred_at: string}> */
    private function recentChurchActivities(ScopeReference $scope, int $limit): array
    {
        $activities = collect($this->recentAuditActivities($scope, $limit, ['church.%', 'membership.%', 'first_timer.%']));

        if ($activities->isEmpty()) {
            $firstTimer = $this->firstTimerQuery($scope)->latest('registered_at')->first();
            if ($firstTimer !== null) {
                $activities->push([
                    'title' => 'First timer registered',
                    'detail' => $firstTimer->public_id,
                    'occurred_at' => $firstTimer->registered_at?->toIso8601String() ?? now()->toIso8601String(),
                ]);
            }
        }

        return $activities->take($limit)->values()->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function monthlySeries(Builder $query, string $column, DashboardPeriod $period): array
    {
        $start = $period->from->startOfMonth();
        $end = $period->to->startOfMonth();
        $months = max(1, min(24, ((int) $start->diffInMonths($end)) + 1));
        $rows = (clone $query)
            ->whereBetween($column, [$period->from, $period->to])
            ->selectRaw($this->monthPeriodSelect($column).', COUNT(*) as aggregate')
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('aggregate', 'period');

        $series = [];
        for ($index = 0; $index < $months; $index++) {
            $month = $start->addMonths($index);
            $key = $month->format('Y-m');
            $series[] = [
                'label' => $month->format('M'),
                'value' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $series;
    }

    /** @return list<array{label: string, value: int}> */
    private function monthlySumSeries(Builder $query, string $column, string $sumColumn, DashboardPeriod $period): array
    {
        $start = $period->from->startOfMonth();
        $end = $period->to->startOfMonth();
        $months = max(1, min(24, ((int) $start->diffInMonths($end)) + 1));
        $rows = (clone $query)
            ->whereBetween($column, [$period->from, $period->to])
            ->selectRaw($this->monthPeriodSelect($column).', SUM('.$sumColumn.') as aggregate')
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('aggregate', 'period');

        $series = [];
        for ($index = 0; $index < $months; $index++) {
            $month = $start->addMonths($index);
            $key = $month->format('Y-m');
            $series[] = [
                'label' => $month->format('M'),
                'value' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $series;
    }

    private function monthPeriodSelect(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'sqlite'
          ? "strftime('%Y-%m', {$column}) as period"
          : "DATE_FORMAT({$column}, '%Y-%m') as period";
    }

    /** @param Builder<Church> $query */
    private function distinctPeriodTrend(Builder $query, string $distinctExpression, string $column, DashboardPeriod $period): ?string
    {
        $previous = $period->previous();
        $current = $this->distinctChurchGeographyCount(
            (clone $query)->whereBetween($column, [$period->from, $period->to]),
            $distinctExpression,
        );
        $prior = $this->distinctChurchGeographyCount(
            (clone $query)->whereBetween($column, [$previous->from, $previous->to]),
            $distinctExpression,
        );

        if ($prior === 0) {
            return $current > 0 ? '+'.$this->formatNumber($current) : null;
        }

        $delta = round((($current - $prior) / $prior) * 100, 1);

        return ($delta >= 0 ? '+' : '').$delta.'%';
    }

    private function periodTrend(Builder $query, string $column, ?DashboardPeriod $period = null): ?string
    {
        if ($period !== null) {
            $previous = $period->previous();
            $current = (clone $query)->whereBetween($column, [$period->from, $period->to])->count();
            $prior = (clone $query)->whereBetween($column, [$previous->from, $previous->to])->count();
        } else {
            $currentStart = now()->subDays(30);
            $previousStart = now()->subDays(60);
            $current = (clone $query)->where($column, '>=', $currentStart)->count();
            $prior = (clone $query)->whereBetween($column, [$previousStart, $currentStart])->count();
        }

        if ($prior === 0) {
            return $current > 0 ? '+'.$this->formatNumber($current) : null;
        }

        $delta = round((($current - $prior) / $prior) * 100, 1);

        return ($delta >= 0 ? '+' : '').$delta.'%';
    }

    private function formatNumber(int $value): string
    {
        return number_format($value);
    }

    private function formatCurrency(int $minor, string $currency = 'NGN'): string
    {
        $symbol = match (strtoupper($currency)) {
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => '₦',
        };

        return $symbol.number_format($minor / 100, 0);
    }

    /** @return array<int, int>|null */
    private function churchIds(ScopeReference $scope): ?array
    {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return null;
        }

        $query = Church::query();

        match ($scope->type) {
            'country' => $query->whereHas('administrativeUnit.country', fn (Builder $countryQuery) => $countryQuery->where('public_id', $scope->key)),
            'administrative_unit' => $query->whereIn('administrative_unit_id', $this->administrativeUnitSubtreeIds($scope->key)),
            'church' => $query->where('public_id', $scope->key),
            'home_church' => $query->whereHas('homeChurches', fn (Builder $homeChurchQuery) => $homeChurchQuery->where('public_id', $scope->key)),
            default => $query->whereRaw('1 = 0'),
        };

        return $query->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /** @return array<int, int>|null */
    private function crusadeIds(ScopeReference $scope): ?array
    {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return null;
        }

        $query = Crusade::query();

        match ($scope->type) {
            'country' => $query->whereHas('location.administrativeUnit.country', fn (Builder $countryQuery) => $countryQuery->where('public_id', $scope->key)),
            'administrative_unit' => $query->whereHas('location', fn (Builder $locationQuery) => $locationQuery->whereIn('administrative_unit_id', $this->administrativeUnitSubtreeIds($scope->key))),
            'mission_crusade' => $query->where('public_id', $scope->key),
            default => $query->whereRaw('1 = 0'),
        };

        return $query->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /** @return array<int, int> */
    private function administrativeUnitSubtreeIds(string $publicId): array
    {
        $root = AdministrativeUnit::query()->select(['id', 'country_id'])->where('public_id', $publicId)->first();

        if ($root === null) {
            return [];
        }

        $frontier = [$root->id];
        $ids = [];

        while ($frontier !== []) {
            $ids = array_merge($ids, $frontier);
            $frontier = AdministrativeUnit::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        }

        return array_values(array_unique($ids));
    }

    /** @param Builder<Model> $query */
    private function applyPersonChurchScope(Builder $query, ScopeReference $scope): void
    {
        $churchIds = $this->churchIds($scope);
        if ($churchIds === null) {
            return;
        }

        $query->whereHas('person.memberships', function (Builder $membershipQuery) use ($churchIds): void {
            $membershipQuery->whereIn('church_id', $churchIds);
        });
    }

    /** @param Builder<Model> $query */
    private function applyPayerChurchScope(Builder $query, ScopeReference $scope): void
    {
        $churchIds = $this->churchIds($scope);
        if ($churchIds === null) {
            return;
        }

        if ($query->getModel() instanceof PaymentTransaction) {
            $query->whereHas('intent.payer.memberships', function (Builder $membershipQuery) use ($churchIds): void {
                $membershipQuery->whereIn('church_id', $churchIds);
            });

            return;
        }

        $query->whereHas('payer.memberships', function (Builder $membershipQuery) use ($churchIds): void {
            $membershipQuery->whereIn('church_id', $churchIds);
        });
    }

    /** @param Builder<Model> $query */
    private function applyAuditScope(Builder $query, ScopeReference $scope): void
    {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return;
        }

        $query->where('scope_type', $scope->type)->where('scope_id', $scope->key);
    }

    /** @param Builder<Model> $query */
    private function applyAuthorizationScope(Builder $query, ScopeReference $scope): void
    {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return;
        }

        $query->where('scope_type', $scope->type)->where('scope_key', $scope->key);
    }

    /** @param Builder<Model> $query */
    private function constrainToPeriod(Builder $query, string $column, DashboardPeriod $period): void
    {
        $query->whereBetween($column, [$period->from, $period->to]);
    }
}

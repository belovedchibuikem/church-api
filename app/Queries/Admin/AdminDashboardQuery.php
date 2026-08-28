<?php

namespace App\Queries\Admin;

use App\Admin\AdminDashboardModule;
use App\Church\ChurchMembershipStatus;
use App\Church\HomeChurchApplicationStatus;
use App\Kca\KcaApplicationState;
use App\Mission\MissionSoulJourneyStatus;
use App\Models\AccessDecision;
use App\Models\AdministrativeUnit;
use App\Models\AuditEvent;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\Country;
use App\Models\Crusade;
use App\Models\EventAttendance;
use App\Models\FirstTimer;
use App\Models\HomeChurch;
use App\Models\HomeChurchApplication;
use App\Models\KcaApplication;
use App\Models\KcaCertificate;
use App\Models\KcaEnrollment;
use App\Models\Location;
use App\Models\MissionSoulJourney;
use App\Models\PaymentTransaction;
use App\Models\PressPublication;
use App\Models\PressTranslation;
use App\Models\User;
use App\Press\PressPublicationStatus;
use App\Support\Authorization\ScopeReference;
use App\Support\Identity\PersonDisplayName;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardQuery
{
  public function summarize(AdminDashboardModule $module, ScopeReference $scope): array
  {
    return match ($module) {
      AdminDashboardModule::Global => $this->global($scope),
      AdminDashboardModule::Geography => $this->geography($scope),
      AdminDashboardModule::HomeChurches => $this->homeChurches($scope),
      AdminDashboardModule::Church => $this->church($scope),
      AdminDashboardModule::Kca => $this->kca($scope),
      AdminDashboardModule::Mission => $this->mission($scope),
      AdminDashboardModule::Press => $this->press($scope),
      AdminDashboardModule::Finance => $this->finance($scope),
      AdminDashboardModule::Communications => $this->communications($scope),
      AdminDashboardModule::Reports => $this->reports($scope),
      AdminDashboardModule::Security => $this->security($scope),
    };
  }

  private function global(ScopeReference $scope): array
  {
    $churchQuery = $this->churchQuery($scope);
    $homeChurchQuery = $this->homeChurchQuery($scope);
    $membershipQuery = $this->membershipQuery($scope);
    $countryQuery = Country::query();

    return [
      'metrics' => [
        $this->metric('Total Churches', (clone $churchQuery)->count(), (clone $churchQuery), 'created_at'),
        $this->metric('Home Churches', (clone $homeChurchQuery)->count(), (clone $homeChurchQuery), 'created_at'),
        $this->metric('Members', (clone $membershipQuery)->count(), (clone $membershipQuery), 'joined_at'),
        $this->metric('Countries', (clone $countryQuery)->count(), (clone $countryQuery), 'created_at'),
      ],
      'breakdown' => $this->topCountriesByChurches($scope, 5),
      'series' => $this->monthlySeries((clone $churchQuery), 'created_at', 6),
      'recent_activities' => $this->recentAuditActivities($scope, 4),
    ];
  }

  private function geography(ScopeReference $scope): array
  {
    $countryQuery = Country::query();
    $this->applyCountryScope($countryQuery, $scope);
    $unitQuery = AdministrativeUnit::query();
    $this->applyAdministrativeUnitScope($unitQuery, $scope);
    $locationQuery = Location::query();
    $this->applyLocationScope($locationQuery, $scope);
    $churchQuery = $this->churchQuery($scope);
    $homeChurchQuery = $this->homeChurchQuery($scope);
    $membershipQuery = $this->membershipQuery($scope);
    $leaders = (clone $homeChurchQuery)->whereNotNull('leader_person_id')->count();

    return [
      'metrics' => [
        $this->metric('Countries', (clone $countryQuery)->count(), (clone $countryQuery), 'created_at'),
        $this->metric('Regions / States', (clone $unitQuery)->count(), (clone $unitQuery), 'created_at'),
        $this->metric('Local Areas', (clone $locationQuery)->count(), (clone $locationQuery), 'created_at'),
        $this->metric('Churches', (clone $churchQuery)->count(), (clone $churchQuery), 'created_at'),
      ],
      'breakdown' => [
        $this->breakdownItem('Active Churches', (clone $churchQuery)->count()),
        $this->breakdownItem('Home Churches', (clone $homeChurchQuery)->count()),
        $this->breakdownItem('Members', (clone $membershipQuery)->count()),
        $this->breakdownItem('Leaders', $leaders),
      ],
      'series' => $this->monthlySeries((clone $churchQuery), 'created_at', 6),
      'recent_activities' => $this->recentAuditActivities($scope, 4),
    ];
  }

  private function homeChurches(ScopeReference $scope): array
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

    return [
      'metrics' => [
        $this->metric('Total Home Churches', (clone $homeChurchQuery)->count(), (clone $homeChurchQuery), 'created_at'),
        $this->metric('Total Members', (clone $membershipQuery)->whereNotNull('home_church_id')->count(), (clone $membershipQuery)->whereNotNull('home_church_id'), 'joined_at'),
        $this->metric('New Applications', (clone $applicationQuery)->whereIn('status', [
          HomeChurchApplicationStatus::Submitted->value,
          HomeChurchApplicationStatus::UnderReview->value,
        ])->count(), (clone $applicationQuery), 'created_at'),
        $this->metric('Active This Week', $activeThisWeek),
      ],
      'breakdown' => $breakdown,
      'donut' => [
        'value' => $this->formatNumber((int) $statusBreakdown->sum()),
        'label' => 'Total',
      ],
      'series' => $this->monthlySeries((clone $homeChurchQuery), 'created_at', 6),
      'recent_activities' => $this->recentAuditActivities($scope, 4, [
        'home_church.%',
        'church.home_church%',
      ]),
    ];
  }

  private function church(ScopeReference $scope): array
  {
    $membershipQuery = $this->membershipQuery($scope);
    $firstTimerQuery = $this->firstTimerQuery($scope);
    $converted = (clone $firstTimerQuery)->whereNotNull('contacted_at')->count();
    $attendanceCount = $this->scopedEventAttendanceCount($scope);
    $withHomeChurch = (clone $membershipQuery)->whereNotNull('home_church_id')->count();
    $withoutHomeChurch = (clone $membershipQuery)->whereNull('home_church_id')->count();
    $totalMembers = max(1, (clone $membershipQuery)->count());

    return [
      'metrics' => [
        $this->metric('Total Members', (clone $membershipQuery)->count(), (clone $membershipQuery), 'joined_at'),
        $this->metric('First Timers', (clone $firstTimerQuery)->count(), (clone $firstTimerQuery), 'registered_at'),
        $this->metric('Converts', $converted, (clone $firstTimerQuery)->whereNotNull('contacted_at'), 'contacted_at'),
        $this->metric('Attendance (Avg)', $attendanceCount),
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
      'series' => $this->monthlySeries((clone $membershipQuery), 'joined_at', 6),
      'recent_activities' => $this->recentChurchActivities($scope, 4),
    ];
  }

  private function kca(ScopeReference $scope): array
  {
    unset($scope);
    $applicationQuery = KcaApplication::query();
    $enrollmentQuery = KcaEnrollment::query();
    $certificateQuery = KcaCertificate::query();
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
        $this->metric('Applications', (clone $applicationQuery)->count(), (clone $applicationQuery), 'received_at'),
        $this->metric('Under Review', (clone $applicationQuery)->where('status', KcaApplicationState::Reviewed->value)->count()),
        $this->metric('Accepted', (clone $applicationQuery)->whereIn('status', [
          KcaApplicationState::Accepted->value,
          KcaApplicationState::ProvisionallyAccepted->value,
        ])->count()),
        $this->metric('Enrollments', (clone $enrollmentQuery)->count(), (clone $enrollmentQuery), 'starts_on'),
      ],
      'breakdown' => $this->statusBreakdownItems($statusBreakdown),
      'donut' => [
        'value' => $this->formatNumber((clone $applicationQuery)->count()),
        'label' => 'Applications',
      ],
      'series' => $this->monthlySeries((clone $applicationQuery), 'received_at', 6),
      'recent_rows' => $recent,
    ];
  }

  private function mission(ScopeReference $scope): array
  {
    $crusadeQuery = $this->crusadeQuery($scope);
    $soulQuery = $this->soulQuery($scope);
    $won = (clone $soulQuery)->where('status', MissionSoulJourneyStatus::FollowUpCompleted->value)->count();
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
        $this->metric('Total Crusades', (clone $crusadeQuery)->count(), (clone $crusadeQuery), 'starts_at'),
        $this->metric('Souls Captured', (clone $soulQuery)->count(), (clone $soulQuery), 'captured_at'),
        $this->metric('Souls Won', $won, (clone $soulQuery)->where('status', MissionSoulJourneyStatus::FollowUpCompleted->value), 'updated_at'),
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
      'series' => $this->monthlySeries((clone $soulQuery), 'captured_at', 6),
      'recent_rows' => $topCrusades,
    ];
  }

  private function press(ScopeReference $scope): array
  {
    unset($scope);
    $publicationQuery = PressPublication::query();
    $translationQuery = PressTranslation::query();
    $published = (clone $publicationQuery)->where('status', PressPublicationStatus::Published->value)->count();
    $inProduction = (clone $publicationQuery)->whereNotIn('status', [
      PressPublicationStatus::Published->value,
      PressPublicationStatus::Distribution->value,
      PressPublicationStatus::Manuscript->value,
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
        $this->metric('Publications', (clone $publicationQuery)->count(), (clone $publicationQuery), 'updated_at'),
        $this->metric('Manuscripts', (clone $publicationQuery)->where('status', PressPublicationStatus::Manuscript->value)->count()),
        $this->metric('In Production', $inProduction),
        $this->metric('Published', $published, (clone $publicationQuery)->where('status', PressPublicationStatus::Published->value), 'updated_at'),
      ],
      'breakdown' => $topPublications,
      'series' => $this->monthlySeries((clone $publicationQuery), 'updated_at', 6),
    ];
  }

  private function finance(ScopeReference $scope): array
  {
    unset($scope);
    $transactionQuery = PaymentTransaction::query();
    $intentQuery = \App\Models\PaymentIntent::query();
    $totalMinor = (int) (clone $transactionQuery)->sum('amount_minor');
    $donorCount = (int) (clone $intentQuery)->distinct('payer_person_id')->count('payer_person_id');
    $transactionCount = (clone $transactionQuery)->count();
    $averageMinor = $transactionCount > 0 ? (int) round($totalMinor / $transactionCount) : 0;

    return [
      'metrics' => [
        $this->metric('Total Receipts', $this->formatCurrency($totalMinor), null, null, $totalMinor, (clone $transactionQuery), 'occurred_at'),
        $this->metric('Total Transactions', $transactionCount, (clone $transactionQuery), 'occurred_at'),
        $this->metric('Total Donors', $donorCount),
        $this->metric('Average Gift', $this->formatCurrency($averageMinor)),
      ],
      'breakdown' => [
        $this->breakdownItem('Transactions', $this->formatNumber($transactionCount)),
        $this->breakdownItem('Receipts', $this->formatCurrency($totalMinor)),
        $this->breakdownItem('Donors', $this->formatNumber($donorCount)),
      ],
      'series' => $this->monthlySumSeries((clone $transactionQuery), 'occurred_at', 'amount_minor', 6),
    ];
  }

  private function communications(ScopeReference $scope): array
  {
    unset($scope);
    $broadcastQuery = CommunicationBroadcast::query();
    $deliveryQuery = CommunicationDeliveryAttempt::query();
    $delivered = (clone $deliveryQuery)->where('status', 'succeeded')->count();
    $failed = (clone $deliveryQuery)->where('status', 'failed')->count();
    $pending = (clone $deliveryQuery)->where('status', 'pending')->count();

    $recent = (clone $broadcastQuery)
      ->latest('created_at')
      ->limit(5)
      ->get()
      ->map(fn (CommunicationBroadcast $broadcast): array => $this->breakdownItem(
        $broadcast->purpose,
        ucfirst((string) $broadcast->status->value),
      ))
      ->all();

    return [
      'metrics' => [
        $this->metric('Messages Sent', (clone $deliveryQuery)->count(), (clone $deliveryQuery), 'created_at'),
        $this->metric('Delivered', $delivered, (clone $deliveryQuery)->where('status', 'succeeded'), 'created_at'),
        $this->metric('Failed', $failed),
        $this->metric('Pending', $pending),
      ],
      'breakdown' => $recent,
      'series' => $this->monthlySeries((clone $deliveryQuery), 'created_at', 6),
    ];
  }

  private function reports(ScopeReference $scope): array
  {
    $churchQuery = $this->churchQuery($scope);
    $homeChurchQuery = $this->homeChurchQuery($scope);
    $membershipQuery = $this->membershipQuery($scope);
    $countryQuery = Country::query();
    $this->applyCountryScope($countryQuery, $scope);

    return [
      'metrics' => [
        $this->metric('Total People', (clone $membershipQuery)->count(), (clone $membershipQuery), 'joined_at'),
        $this->metric('Active Churches', (clone $churchQuery)->count(), (clone $churchQuery), 'created_at'),
        $this->metric('Home Churches', (clone $homeChurchQuery)->count(), (clone $homeChurchQuery), 'created_at'),
        $this->metric('Countries', (clone $countryQuery)->count()),
      ],
      'breakdown' => $this->topCountriesByChurches($scope, 5),
      'series' => $this->monthlySeries((clone $membershipQuery), 'joined_at', 6),
    ];
  }

  private function security(ScopeReference $scope): array
  {
    unset($scope);
    $userQuery = User::query();
    $activeUsers = (clone $userQuery)->where('account_status', 'active')->count();
    $failedLogins = AccessDecision::query()
      ->where('allowed', false)
      ->where('decided_at', '>=', now()->subDays(30))
      ->count();
    $securityAlerts = AuditEvent::query()
      ->where('action', 'like', 'security.%')
      ->where('occurred_at', '>=', now()->subDays(30))
      ->count();

    $recentAlerts = AuditEvent::query()
      ->where(function (Builder $query): void {
        $query->where('action', 'like', 'security.%')
          ->orWhere('action', 'like', '%.denied');
      })
      ->latest('occurred_at')
      ->limit(5)
      ->get()
      ->map(fn (AuditEvent $event): array => $this->breakdownItem(
        str_replace('.', ' ', $event->action),
        $event->occurred_at?->diffForHumans() ?? '—',
      ))
      ->all();

    return [
      'metrics' => [
        $this->metric('User Accounts', (clone $userQuery)->count(), (clone $userQuery), 'created_at'),
        $this->metric('Active Users', $activeUsers),
        $this->metric('Failed Logins', $failedLogins),
        $this->metric('Security Alerts', $securityAlerts),
      ],
      'breakdown' => $recentAlerts,
      'series' => $this->monthlySeries(AuditEvent::query(), 'occurred_at', 6),
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

  /** @return array<int, array{label: string, value: string, trend?: string}> */
  private function metric(
    string $label,
    int|string $value,
    ?Builder $trendQuery = null,
    ?string $trendColumn = null,
    ?int $rawValue = null,
    ?Builder $alternateTrendQuery = null,
    ?string $alternateTrendColumn = null,
  ): array {
    $metric = [
      'label' => $label,
      'value' => is_int($value) ? $this->formatNumber($value) : (string) $value,
    ];

    $query = $alternateTrendQuery ?? $trendQuery;
    $column = $alternateTrendColumn ?? $trendColumn;

    if ($query !== null && $column !== null) {
      $trend = $this->periodTrend($query, $column);
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

  /** @return list<array{label: string, value: string}> */
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

    return $query->get()->map(
      fn ($row): array => $this->breakdownItem((string) $row->name, (int) $row->aggregate),
    )->all();
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
  private function monthlySeries(Builder $query, string $column, int $months): array
  {
    $start = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
    $rows = (clone $query)
      ->where($column, '>=', $start)
      ->selectRaw("DATE_FORMAT({$column}, '%Y-%m') as period, COUNT(*) as aggregate")
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
  private function monthlySumSeries(Builder $query, string $column, string $sumColumn, int $months): array
  {
    $start = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
    $rows = (clone $query)
      ->where($column, '>=', $start)
      ->selectRaw("DATE_FORMAT({$column}, '%Y-%m') as period, SUM({$sumColumn}) as aggregate")
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

  private function periodTrend(Builder $query, string $column): ?string
  {
    $currentStart = now()->subDays(30);
    $previousStart = now()->subDays(60);
    $current = (clone $query)->where($column, '>=', $currentStart)->count();
    $previous = (clone $query)->whereBetween($column, [$previousStart, $currentStart])->count();

    if ($previous === 0) {
      return $current > 0 ? '+'.$this->formatNumber($current) : null;
    }

    $delta = round((($current - $previous) / $previous) * 100, 1);

    return ($delta >= 0 ? '+' : '').$delta.'%';
  }

  private function formatNumber(int $value): string
  {
    return number_format($value);
  }

  private function formatCurrency(int $minor): string
  {
    return '₦'.number_format($minor / 100, 0);
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

  private function applyCountryScope(Builder $query, ScopeReference $scope): void
  {
    if ($scope->type === 'country') {
      $query->where('public_id', $scope->key);
    }
  }

  private function applyAdministrativeUnitScope(Builder $query, ScopeReference $scope): void
  {
    if ($scope->type === 'country') {
      $query->whereHas('country', fn (Builder $countryQuery) => $countryQuery->where('public_id', $scope->key));
    }

    if ($scope->type === 'administrative_unit') {
      $query->whereIn('id', $this->administrativeUnitSubtreeIds($scope->key));
    }
  }

  private function applyLocationScope(Builder $query, ScopeReference $scope): void
  {
    if ($scope->type === 'country') {
      $query->whereHas('country', fn (Builder $countryQuery) => $countryQuery->where('public_id', $scope->key));
    }

    if ($scope->type === 'administrative_unit') {
      $query->whereIn('administrative_unit_id', $this->administrativeUnitSubtreeIds($scope->key));
    }
  }
}

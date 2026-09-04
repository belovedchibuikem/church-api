<?php

namespace App\Demo;

use App\Church\ChurchGroupMembershipStatus;
use App\Church\HomeChurchApplicationStatus;
use App\Church\HomeChurchStatus;
use App\Communication\CommunicationBroadcastStatus;
use App\Communication\CommunicationChannel;
use App\Communication\CommunicationDeliveryStatus;
use App\Communication\CommunicationKind;
use App\Events\EventRegistrationStatus;
use App\Files\FileAssetClassification;
use App\Files\FileAssetStatus;
use App\Files\MalwareScanStatus;
use App\Finance\PaymentIntentStatus;
use App\Finance\PaymentReconciliationStatus;
use App\Kca\KcaApplicationState;
use App\Kca\KcaAssignmentState;
use App\Kca\KcaAttendanceStatus;
use App\Media\Actions\AttachMediaAction;
use App\Media\MediaRole;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\AlertRule;
use App\Models\Church;
use App\Models\ChurchAnnouncement;
use App\Models\ChurchDepartment;
use App\Models\ChurchDocument;
use App\Models\ChurchGroup;
use App\Models\ChurchGroupMembership;
use App\Models\ChurchMembership;
use App\Models\ChurchRoleAssignment;
use App\Models\CommunicationAudience;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\CommunicationRecipient;
use App\Models\CommunicationTemplate;
use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\Convert;
use App\Models\CounsellingCase;
use App\Models\Country;
use App\Models\Crusade;
use App\Models\DemoDataset;
use App\Models\EvangelismActivity;
use App\Models\EventRegistration;
use App\Models\FileAsset;
use App\Models\FirstTimer;
use App\Models\FollowUpTask;
use App\Models\HomeChurch;
use App\Models\HomeChurchApplication;
use App\Models\HomeChurchAttendanceRecord;
use App\Models\KcaApplication;
use App\Models\KcaAssignment;
use App\Models\KcaAttendance;
use App\Models\KcaCertificate;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\KcaModule;
use App\Models\KcaYear;
use App\Models\Livestream;
use App\Models\Location;
use App\Models\MinistryEvent;
use App\Models\MissionSoulJourney;
use App\Models\PastoralNeed;
use App\Models\PaymentIntent;
use App\Models\PaymentReceipt;
use App\Models\PaymentReconciliation;
use App\Models\PaymentTransaction;
use App\Models\Person;
use App\Models\PersonProfile;
use App\Models\PrayerRequest;
use App\Models\PressPublication;
use App\Models\PressPublicationContributor;
use App\Models\Role;
use App\Models\Testimony;
use App\Models\User;
use App\Press\PressContributorRole;
use App\Press\PressPublicationAvailability;
use App\Press\PressPublicationFormat;
use App\Press\PressPublicationStatus;
use App\Reporting\AlertSeverity;
use App\Storage\StorageProvider;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\ProvisionAuthorizationBundlesAction;
use App\Support\Authorization\ScopeReference;
use App\Support\Identity\PersonDisplayName;
use App\Support\Kca\KcaCertificateCodeHasher;
use App\Support\Livestream\UpsertLivestreamAction;
use App\Support\Organization\SeedWorldGeographyAction;
use Database\Seeders\ContentPagesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeedDemoDatasetAction
{
    public const PASSWORD = 'DemoPass!2026';

    public function __construct(
        private readonly DemoDatasetRegistrar $registrar,
        private readonly ProvisionAuthorizationBundlesAction $provisionBundles,
        private readonly AssignRoleToUserAction $assignRole,
        private readonly AssignScopeToRoleAssignmentAction $assignScope,
        private readonly AttachMediaAction $attachMedia,
        private readonly KcaCertificateCodeHasher $certificateHasher,
    ) {}

    /**
     * @return array{seeded: bool, skipped: bool, summary: array<string, mixed>}
     */
    public function handle(bool $force = false): array
    {
        $existing = DemoDataset::query()->where('dataset_key', DemoDataset::KEY)->first();
        if ($existing !== null && ! $force) {
            return ['seeded' => false, 'skipped' => true, 'summary' => $existing->summary ?? []];
        }

        if ($force && $existing !== null) {
            app(WipeDemoDatasetAction::class)->handle();
        }

        $this->seedPriorityGeographyCatalogue();

        return DB::transaction(function (): array {
            $this->provisionBundles->handle();
            (new ContentPagesSeeder)->run();

            $palette = $this->palette();
            $countries = $this->geography();
            $admin = $this->namedUser('Daniel', 'David', 'admin@familyhouse.demo', 'Pastor Daniel David');
            $pastor = $this->namedUser('Grace', 'Ezekiel', 'pastor@familyhouse.demo', 'Pastor Grace Ezekiel');
            $student = $this->namedUser('Samuel', 'David', 'member@familyhouse.demo', 'Samuel David');
            $this->grantAllAdminRoles($admin);
            $this->grantRoles($student, [AuthorizationBundleCatalog::MEMBER_SECURITY_ROLE]);

            $leaders = [
                $this->namedPerson('Samuel', 'Ade'),
                $this->namedPerson('Mary', 'Okoro'),
                $this->namedPerson('James', 'Kariuki'),
                $this->namedPerson('Paul', 'Okoro'),
                $this->namedPerson('Grace', 'Mensah'),
                $this->namedPerson('Ada', 'Okoro'),
            ];
            $members = [];
            foreach ([
                ['John', 'Samuel'], ['Faith', 'Akin'], ['David', 'Johnson'], ['Esther', 'James'],
                ['Linda', 'Johnson'], ['Ayo', 'Peter'], ['Daniel', 'Adedeji'],                 ['Chioma', 'Eze'],
                ['Kwame', 'Boateng'], ['Amina', 'Hassan'], ['Tunde', 'Bakare'], ['Ngozi', 'Okafor'],
                ['Blessing', 'Okeke'], ['Ibrahim', 'Yusuf'], ['Ruth', 'Mwangi'], ['Kojo', 'Asante'],
            ] as [$given, $family]) {
                $members[] = $this->namedPerson($given, $family);
            }

            $churches = $this->churches($countries, $palette);
            $this->grantChurchAdministrator($pastor, $churches[0]);
            $homeChurches = $this->homeChurches($churches, $pastor, $leaders);
            $this->livestream($churches[2] ?? $churches[0], $pastor->person);
            $this->memberships($churches, $homeChurches, array_merge([$admin->person, $pastor->person, $student->person], $leaders, $members));
            $this->ensureMembership($pastor->person, $churches[0]);
            $this->churchCommunity($churches, $pastor->person, array_merge([$student->person], array_slice($members, 0, 6)));
            $this->firstTimersAndFollowUps($churches[0], $homeChurches[0], $members, $pastor->person);
            $this->ministryExtras($churches[0], $homeChurches[0], $members, $pastor->person, $leaders);
            $this->homeChurchApplications($churches[0], $members);
            $events = $this->events($countries['ng']['locations']['ikeja'], $palette);
            $this->eventRegistrations($events, array_merge([$student->person, $pastor->person], array_slice($members, 0, 8)));
            $crusades = $this->crusades($countries, $palette);
            $this->souls($crusades[0], $churches[0], array_slice($members, 0, 8));
            $this->kca($admin, $student, $members, $palette);
            $this->press($palette, array_merge([$pastor->person, $admin->person], array_slice($members, 0, 4)));
            $this->pastoralCare(array_merge([$student->person], $members));
            $this->finance(array_merge([$student->person, $pastor->person], array_slice($members, 0, 6)));
            $this->communications($admin);
            $this->alerts($admin);
            $this->attachContentImages($palette);
            $this->attachPeopleAvatars(array_merge(
                [$admin->person, $pastor->person, $student->person],
                $leaders,
            ), $palette);

            $summary = [
                'churches' => Church::query()->count(),
                'home_churches' => HomeChurch::query()->count(),
                'events' => MinistryEvent::query()->count(),
                'crusades' => Crusade::query()->count(),
                'people' => Person::query()->count(),
                'users' => User::query()->count(),
                'press' => PressPublication::query()->count(),
                'media' => FileAsset::query()->count(),
                'livestreams' => Livestream::query()->count(),
                'accounts' => [
                    'admin@familyhouse.demo',
                    'pastor@familyhouse.demo',
                    'member@familyhouse.demo',
                ],
            ];

            $dataset = DemoDataset::query()->where('dataset_key', DemoDataset::KEY)->first() ?? new DemoDataset;
            $dataset->forceFill([
                'dataset_key' => DemoDataset::KEY,
                'seeded_at' => now()->utc(),
                'summary' => $summary,
            ])->save();

            return ['seeded' => true, 'skipped' => false, 'summary' => $summary];
        }, attempts: 1);
    }

    /** @return array<string, array{0: int, 1: int, 2: int}> */
    private function palette(): array
    {
        return [
            'violet' => [56, 18, 184],
            'gold' => [212, 160, 48],
            'green' => [21, 147, 91],
            'navy' => [10, 27, 53],
            'rose' => [176, 62, 92],
            'teal' => [20, 120, 140],
            'amber' => [196, 120, 32],
            'indigo' => [72, 48, 160],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function geography(): array
    {
        $nigeria = $this->country('NG', 'Nigeria');
        $ghana = $this->country('GH', 'Ghana');
        $kenya = $this->country('KE', 'Kenya');

        $ngState = $this->level($nigeria, 'state', 'State', 10);
        $ngLga = $this->level($nigeria, 'local_government', 'Local Government', 20);
        $ghRegion = $this->level($ghana, 'region', 'Region', 10);
        $keCounty = $this->level($kenya, 'county', 'County', 10);

        $lagos = $this->unit($nigeria, $ngState, 'Lagos', 'NG-LA');
        $enugu = $this->unit($nigeria, $ngState, 'Enugu', 'NG-EN');
        $ikeja = $this->unit($nigeria, $ngLga, 'Ikeja', 'NG-LA-IKE', $lagos);
        $lekki = $this->unit($nigeria, $ngLga, 'Eti-Osa', 'NG-LA-ETI', $lagos);
        $enuguCity = $this->unit($nigeria, $ngLga, 'Enugu East', 'NG-EN-EAS', $enugu);
        $accra = $this->unit($ghana, $ghRegion, 'Greater Accra', 'GH-AA');
        $nairobi = $this->unit($kenya, $keCounty, 'Nairobi', 'KE-110');

        return [
            'ng' => [
                'country' => $nigeria,
                'units' => compact('lagos', 'ikeja', 'lekki', 'enugu', 'enuguCity'),
                'locations' => [
                    'ikeja' => $this->location($nigeria, $ikeja, 'Family House Ikeja Campus', '12 Adeniyi Jones Ave', 'Ikeja', 'Africa/Lagos', 6.6018, 3.3515),
                    'allen' => $this->location($nigeria, $ikeja, 'Allen Avenue Gathering', 'Allen Ave', 'Ikeja', 'Africa/Lagos', 6.6031, 3.3570),
                    'lekki' => $this->location($nigeria, $lekki, 'Family House Lekki Campus', 'Lekki Phase 1', 'Lekki', 'Africa/Lagos', 6.4474, 3.4723),
                    'enugu' => $this->location($nigeria, $enuguCity, 'Building Hope Site', 'Independence Layout', 'Enugu', 'Africa/Lagos', 6.4527, 7.5103),
                    'stadium' => $this->location($nigeria, $lagos, 'National Stadium', 'Surulere', 'Lagos', 'Africa/Lagos', 6.4969, 3.3614),
                    'online' => $this->location($nigeria, $ikeja, 'Family House Online Hub', 'Broadcast Centre', 'Ikeja', 'Africa/Lagos', 6.6018, 3.3515),
                ],
            ],
            'gh' => [
                'country' => $ghana,
                'units' => compact('accra'),
                'locations' => [
                    'accra' => $this->location($ghana, $accra, 'Independence Square', 'High Street', 'Accra', 'Africa/Accra', 5.5480, -0.1969),
                ],
            ],
            'ke' => [
                'country' => $kenya,
                'units' => compact('nairobi'),
                'locations' => [
                    'nairobi' => $this->location($kenya, $nairobi, 'Uhuru Gardens', 'Langata Road', 'Nairobi', 'Africa/Nairobi', -1.3187, 36.7970),
                ],
            ],
        ];
    }

    /**
     * Full civic catalogue (all NG states/LGAs, plus GH/KE) is platform data, not demo rows.
     */
    private function seedPriorityGeographyCatalogue(): void
    {
        $path = database_path('data/geography/world-states.json');
        if (! is_file($path)) {
            return;
        }

        try {
            app(SeedWorldGeographyAction::class)->handle(
                onlyIsos: ['NG', 'GH', 'KE'],
                withLocalities: true,
            );
        } catch (\Throwable) {
            // Demo churches still attach to the Lagos/Enugu units created below.
        }
    }

    /**
     * @param  array<string, mixed>  $countries
     * @param  array<string, array{0: int, 1: int, 2: int}>  $palette
     * @return list<Church>
     */
    private function churches(array $countries, array $palette): array
    {
        $definitions = [
            ['Family House Church Ikeja', $countries['ng']['units']['ikeja'], $countries['ng']['locations']['ikeja'], $palette['violet']],
            ['Family House Church Lekki', $countries['ng']['units']['lekki'], $countries['ng']['locations']['lekki'], $palette['gold']],
            ['Family House Online Church', $countries['ng']['units']['ikeja'], $countries['ng']['locations']['online'], $palette['navy']],
            ['Family House Church Enugu', $countries['ng']['units']['enuguCity'], $countries['ng']['locations']['enugu'], $palette['green']],
            ['Family House Church Accra', $countries['gh']['units']['accra'], $countries['gh']['locations']['accra'], $palette['teal']],
            ['Family House Church Nairobi', $countries['ke']['units']['nairobi'], $countries['ke']['locations']['nairobi'], $palette['rose']],
        ];

        $churches = [];
        foreach ($definitions as [$name, $unit, $location, $color]) {
            $church = Church::query()->firstOrNew([
                'location_id' => $location->getKey(),
                'name' => $name,
            ]);
            $church->forceFill([
                'administrative_unit_id' => $unit->getKey(),
                'published_at' => now()->utc()->subDays(30),
            ])->save();
            $this->remember($church);
            $this->attachImage($church, MediaRole::Cover, $color, Str::slug($name).'.png');
            $churches[] = $church;
        }

        return $churches;
    }

    private function livestream(Church $church, Person $host): void
    {
        $youtubeUrl = env(
            'FHC_DEMO_YOUTUBE_LIVE_URL',
            'https://www.youtube.com/watch?v=EngW7tLk6R8',
        );

        app(UpsertLivestreamAction::class)->handle([
            'title' => 'Sunday Celebration Live',
            'subtitle' => $church->name,
            'host_name' => PersonDisplayName::of($host) ?: 'Family House',
            'youtube_url' => $youtubeUrl,
            'status' => 'live',
            'church_id' => $church->public_id,
            'viewer_count' => 0,
            'reaction_count' => 0,
            'starts_at' => now()->utc()->subMinutes(15)->toIso8601String(),
        ]);
    }

    /**
     * @param  list<Church>  $churches
     * @param  list<Person>  $leaders
     * @return list<HomeChurch>
     */
    private function homeChurches(array $churches, User $pastor, array $leaders): array
    {
        $rows = [
            ['Family House Home Church – Allen', $churches[0], $pastor->person, $churches[0]->location_id],
            ['Grace Home Church', $churches[0], $leaders[1], $churches[0]->location_id],
            ['Lekki Fellowship', $churches[1], $leaders[0], $churches[1]->location_id],
        ];
        $created = [];
        foreach ($rows as $index => [$name, $church, $leader, $locationId]) {
            $home = HomeChurch::query()->firstOrNew(['name' => $name, 'church_id' => $church->getKey()]);
            $home->forceFill([
                'leader_person_id' => $leader->getKey(),
                'location_id' => $locationId,
                'administrative_unit_id' => $church->administrative_unit_id,
                'status' => HomeChurchStatus::Active,
            ])->save();
            $this->remember($home);
            $this->attachImage($home, MediaRole::Cover, [80 + $index * 20, 40, 140], Str::slug($name).'.png');
            $created[] = $home;
        }

        return $created;
    }

    /**
     * @param  list<Church>  $churches
     * @param  list<Person>  $members
     */
    private function churchCommunity(array $churches, Person $leader, array $members): void
    {
        $church = $churches[0];
        $groupSpecs = [
            ['Faith Builders', 'Weekly discipleship small group'],
            ['Young Adults', 'Fellowship for young adults'],
            ['Men of Valor', 'Men\'s mentoring circle'],
        ];
        foreach ($groupSpecs as $index => [$name, $description]) {
            $group = new ChurchGroup([
                'church_id' => $church->getKey(),
                'name' => $name,
                'description' => $description,
                'leader_person_id' => $leader->getKey(),
                'capacity' => 30,
                'is_published' => true,
            ]);
            $group->save();
            $this->remember($group);

            foreach (array_slice($members, 0, 3 + $index) as $person) {
                $membership = new ChurchGroupMembership([
                    'church_group_id' => $group->getKey(),
                    'person_id' => $person->getKey(),
                    'status' => ChurchGroupMembershipStatus::Active,
                    'joined_at' => now()->subWeeks($index + 1),
                ]);
                $membership->save();
                $this->remember($membership);
            }
        }

        foreach ([
            ['Sunday Celebration Reminder', 'Join us this Sunday at 8:00 AM for celebration service.'],
            ['Workers Meeting', 'All department leaders meet Wednesday at 6:00 PM.'],
            ['Prayer & Fasting Week', 'Corporate prayer and fasting begins Monday.'],
        ] as $index => [$title, $body]) {
            $announcement = new ChurchAnnouncement([
                'church_id' => $church->getKey(),
                'title' => $title,
                'body' => $body,
                'published_at' => now()->subDays($index),
                'created_by_person_id' => $leader->getKey(),
            ]);
            $announcement->save();
            $this->remember($announcement);
        }

        foreach (['Membership Handbook', 'Safeguarding Policy', 'Worship Guidelines'] as $index => $title) {
            $document = new ChurchDocument([
                'church_id' => $church->getKey(),
                'title' => $title,
                'description' => 'Official church document for members.',
                'published_at' => now()->subDays(10 + $index),
            ]);
            $document->save();
            $this->remember($document);
        }
    }

    /**
     * @param  list<Church>  $churches
     * @param  list<HomeChurch>  $homeChurches
     * @param  list<Person>  $people
     */
    private function memberships(array $churches, array $homeChurches, array $people): void
    {
        foreach ($people as $index => $person) {
            $church = $churches[$index % count($churches)];
            $home = $index < 10 ? $homeChurches[$index % count($homeChurches)] : null;
            $membership = ChurchMembership::factory()->create([
                'person_id' => $person->getKey(),
                'church_id' => $church->getKey(),
                'home_church_id' => $home?->getKey(),
                'joined_at' => now()->subMonths(1 + ($index % 10)),
            ]);
            $this->remember($membership);
        }
    }

    /**
     * @param  list<Person>  $members
     */
    private function firstTimersAndFollowUps(Church $church, HomeChurch $homeChurch, array $members, Person $assignee): void
    {
        foreach (array_slice($members, 0, 8) as $index => $person) {
            $firstTimer = FirstTimer::factory()->create([
                'person_id' => $person->getKey(),
                'church_id' => $church->getKey(),
                'home_church_id' => $index < 3 ? $homeChurch->getKey() : null,
                'registered_at' => now()->subDays(2 + $index),
            ]);
            $this->remember($firstTimer);
            $task = FollowUpTask::factory()->create([
                'first_timer_id' => $firstTimer->getKey(),
                'assigned_to_person_id' => $assignee->getKey(),
                'due_at' => now()->addDays($index + 1),
            ]);
            $this->remember($task);
        }
    }

    /**
     * @param  list<Person>  $members
     * @param  list<Person>  $leaders
     */
    private function ministryExtras(Church $church, HomeChurch $homeChurch, array $members, Person $pastor, array $leaders): void
    {
        foreach (array_slice($members, 0, 4) as $index => $person) {
            if ($person->profile) {
                $person->profile->forceFill(['phone' => '+23480000'.str_pad((string) (1000 + $index), 4, '0', STR_PAD_LEFT)])->save();
            }
            $convert = Convert::query()->create([
                'person_id' => $person->getKey(),
                'church_id' => $church->getKey(),
                'home_church_id' => $index < 2 ? $homeChurch->getKey() : null,
                'converted_at' => now()->subDays(14 + $index),
                'baptized_at' => $index % 2 === 0 ? now()->subDays(7 + $index) : null,
                'source' => ['altar_call', 'crusade', 'friend', 'online'][$index],
                'status' => 'active',
                'notes' => 'Demo convert record',
            ]);
            $this->remember($convert);
        }

        $activity = EvangelismActivity::query()->create([
            'church_id' => $church->getKey(),
            'title' => 'Weekend Market Outreach',
            'activity_type' => 'outreach',
            'souls_reached' => 48,
            'decisions' => 6,
            'occurred_at' => now()->subDays(3),
            'status' => 'completed',
            'notes' => 'Demo evangelism activity',
        ]);
        $this->remember($activity);

        $department = ChurchDepartment::query()->create([
            'church_id' => $church->getKey(),
            'name' => 'Ushering',
            'description' => 'Welcome and seating ministry',
            'leader_person_id' => ($leaders[0] ?? $pastor)->getKey(),
            'status' => 'active',
        ]);
        $this->remember($department);

        foreach ([['worker', 'Usher'], ['leader', 'Department Lead'], ['disciple', 'New Disciple']] as $index => [$roleType, $title]) {
            $person = $members[$index] ?? $pastor;
            $assignment = ChurchRoleAssignment::query()->create([
                'church_id' => $church->getKey(),
                'person_id' => $person->getKey(),
                'department_id' => $department->getKey(),
                'role_type' => $roleType,
                'title' => $title,
                'status' => 'active',
                'started_at' => now()->subMonths(1 + $index),
            ]);
            $this->remember($assignment);
        }

        $case = CounsellingCase::query()->create([
            'church_id' => $church->getKey(),
            'client_person_id' => ($members[4] ?? $pastor)->getKey(),
            'counselor_person_id' => $pastor->getKey(),
            'case_type' => 'family',
            'status' => 'open',
            'summary' => 'Demo counselling case summary',
            'opened_at' => now()->subDays(5),
        ]);
        $this->remember($case);

        $testimony = Testimony::query()->create([
            'church_id' => $church->getKey(),
            'person_id' => ($members[1] ?? $pastor)->getKey(),
            'title' => 'Healing and restoration',
            'body' => 'God restored my family after prayer and follow-up from the church.',
            'status' => 'approved',
            'submitted_at' => now()->subDays(2),
            'published_at' => now()->subDay(),
        ]);
        $this->remember($testimony);

        $attendance = HomeChurchAttendanceRecord::query()->create([
            'home_church_id' => $homeChurch->getKey(),
            'service_date' => now()->toDateString(),
            'males' => 11,
            'females' => 7,
            'adults' => 18,
            'children' => 7,
            'first_timers' => 0,
            'notes' => 'Demo attendance record',
        ]);
        $this->remember($attendance);

        $churchNeed = PastoralNeed::query()->create([
            'church_id' => $church->getKey(),
            'category' => 'equipment',
            'summary' => 'Replace sanctuary projector and HDMI switcher',
            'status' => 'open',
        ]);
        $this->remember($churchNeed);

        $homeChurchNeed = PastoralNeed::query()->create([
            'church_id' => $church->getKey(),
            'home_church_id' => $homeChurch->getKey(),
            'person_id' => $pastor->getKey(),
            'category' => 'materials',
            'summary' => 'Bibles and study manuals for new members',
            'status' => 'open',
        ]);
        $this->remember($homeChurchNeed);
    }

    /**
     * @param  list<Person>  $members
     */
    private function homeChurchApplications(Church $church, array $members): void
    {
        $statuses = [
            HomeChurchApplicationStatus::Submitted,
            HomeChurchApplicationStatus::UnderReview,
            HomeChurchApplicationStatus::InterviewOrientation,
        ];
        foreach ($statuses as $index => $status) {
            $application = HomeChurchApplication::factory()->create([
                'applicant_person_id' => $members[$index + 8]->getKey(),
                'church_id' => $church->getKey(),
                'location_id' => $church->location_id,
                'administrative_unit_id' => $church->administrative_unit_id,
                'proposed_name' => ['Victory Home Church', 'Hope Living Room', 'Covenant Circle'][$index],
                'contact_email' => 'homechurch.'.$index.'@familyhouse.demo',
            ]);
            $application->forceFill([
                'status' => $status,
                'status_changed_at' => now()->utc(),
                'active_marker' => 1,
            ])->save();
            $this->remember($application);
        }
    }

    /**
     * @param  array<string, array{0: int, 1: int, 2: int}>  $palette
     * @return list<MinistryEvent>
     */
    private function events(Location $ikeja, array $palette): array
    {
        $rows = [
            ['Kingdom Advancement Conference 2026', 'conference', now()->addWeeks(6), now()->addWeeks(6)->addDays(2), 1500000, $palette['violet']],
            ['Youth Summit 2026', 'youth', now()->addWeeks(8), now()->addWeeks(8)->addHours(8), 500000, $palette['gold']],
            ['Leadership Training', 'training', now()->addWeeks(10), now()->addWeeks(10)->addHours(7), null, $palette['navy']],
            ['Prayer & Fasting Week', 'prayer', now()->addWeeks(12), now()->addWeeks(13), null, $palette['green']],
            ['Workers Retreat', 'training', now()->addWeeks(4), now()->addWeeks(4)->addDays(1), 250000, $palette['teal']],
            ['Sunday Celebration Service', 'worship', now()->next('Sunday')->setTime(10, 0), now()->next('Sunday')->setTime(12, 30), null, $palette['rose']],
            ['Workers Commissioning Night', 'worship', now()->subWeeks(3)->setTime(18, 0), now()->subWeeks(3)->setTime(21, 0), null, $palette['amber']],
            ['Home Church Leaders Forum', 'training', now()->subWeeks(8)->setTime(9, 0), now()->subWeeks(8)->setTime(16, 0), null, $palette['indigo']],
        ];
        $events = [];
        foreach ($rows as [$name, $category, $start, $end, $fee, $color]) {
            $event = MinistryEvent::factory()->published()->create([
                'location_id' => $ikeja->getKey(),
                'category_code' => $category,
                'name' => $name,
                'starts_at' => $start,
                'ends_at' => $end,
                'registration_opens_at' => now()->subDay(),
                'registration_closes_at' => $start->copy()->subHour(),
                'fee_amount_minor' => $fee,
                'fee_currency' => $fee === null ? null : 'NGN',
                'capacity' => 400,
            ]);
            $this->remember($event);
            $this->attachImage($event, MediaRole::Cover, $color, Str::slug($name).'.png');
            $events[] = $event;
        }

        return $events;
    }

    /**
     * @param  list<MinistryEvent>  $events
     * @param  list<Person>  $people
     */
    private function eventRegistrations(array $events, array $people): void
    {
        foreach ($people as $index => $person) {
            $event = $events[$index % 3];
            $registration = EventRegistration::factory()->create([
                'ministry_event_id' => $event->getKey(),
                'person_id' => $person->getKey(),
                'status' => EventRegistrationStatus::Confirmed,
                'idempotency_scope_hash' => hash('sha256', 'demo-reg-'.$person->getKey().'-'.$event->getKey()),
                'ticket_code' => 'EVT-'.Str::upper(Str::random(10)),
            ]);
            $this->remember($registration);
        }
    }

    /**
     * @param  array<string, mixed>  $countries
     * @param  array<string, array{0: int, 1: int, 2: int}>  $palette
     * @return list<Crusade>
     */
    private function crusades(array $countries, array $palette): array
    {
        $rows = [
            ['Lagos Mega Crusade', $countries['ng']['locations']['stadium'], now()->addWeeks(3), now()->addWeeks(3)->addDays(3), $palette['violet']],
            ['Accra Harvest Gathering', $countries['gh']['locations']['accra'], now()->addWeeks(9), now()->addWeeks(9)->addDays(2), $palette['gold']],
            ['Nairobi Kingdom Night', $countries['ke']['locations']['nairobi'], now()->subMonths(2), now()->subMonths(2)->addDays(2), $palette['green']],
        ];
        $crusades = [];
        foreach ($rows as [$name, $location, $start, $end, $color]) {
            $crusade = Crusade::factory()->published()->create([
                'name' => $name,
                'location_id' => $location->getKey(),
                'starts_at' => $start,
                'ends_at' => $end,
            ]);
            $this->remember($crusade);
            $this->attachImage($crusade, MediaRole::Cover, $color, Str::slug($name).'.png');
            $crusades[] = $crusade;
        }

        return $crusades;
    }

    /**
     * @param  list<Person>  $people
     */
    private function souls(Crusade $crusade, Church $church, array $people): void
    {
        foreach ($people as $index => $person) {
            $soul = MissionSoulJourney::factory()->create([
                'crusade_id' => $crusade->getKey(),
                'person_id' => $person->getKey(),
                'connected_church_id' => $index < 4 ? $church->getKey() : null,
                'captured_at' => now()->subDays($index + 1),
            ]);
            $this->remember($soul);
        }
    }

    /**
     * @param  list<Person>  $members
     * @param  array<string, array{0: int, 1: int, 2: int}>  $palette
     */
    private function kca(User $admin, User $student, array $members, array $palette): void
    {
        $year = KcaYear::factory()->create([
            'code' => 'kca-2026',
            'name' => 'KCA 2026',
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
        ]);
        $this->remember($year);
        $cohort = KcaCohort::factory()->create([
            'kca_year_id' => $year->getKey(),
            'code' => 'kca-2026-a',
            'name' => 'Cohort A — Lagos',
        ]);
        $this->remember($cohort);

        $modules = [];
        foreach ([
            ['foundations-of-faith', 'Foundations of Faith'],
            ['kingdom-leadership', 'Kingdom Leadership'],
            ['prayer-and-intercession', 'Prayer and Intercession'],
            ['mission-and-discipleship', 'Mission and Discipleship'],
        ] as $index => [$code, $title]) {
            $module = KcaModule::factory()->create([
                'code' => $code,
                'title' => $title,
                'sequence' => $index + 1,
            ]);
            $this->remember($module);
            $this->attachImage($module, MediaRole::Cover, array_values($palette)[$index], $code.'.png');
            for ($lesson = 1; $lesson <= 3; $lesson++) {
                $row = KcaLesson::factory()->create([
                    'kca_module_id' => $module->getKey(),
                    'code' => $code.'-l'.$lesson,
                    'title' => $title.' · Lesson '.$lesson,
                    'sequence' => $lesson,
                ]);
                $this->remember($row);
            }
            $modules[] = $module;
        }

        $applicants = array_merge([$student->person], array_slice($members, 0, 6));
        foreach ($applicants as $index => $person) {
            $application = KcaApplication::factory()->accepted()->create([
                'person_id' => $person->getKey(),
                'status' => $index < 5 ? KcaApplicationState::Accepted : KcaApplicationState::Reviewed,
            ]);
            $this->remember($application);
            if ($index >= 5) {
                continue;
            }
            $enrollment = KcaEnrollment::factory()->create([
                'kca_application_id' => $application->getKey(),
                'person_id' => $person->getKey(),
                'kca_cohort_id' => $cohort->getKey(),
                'kca_year_id' => $year->getKey(),
                'registration_number' => 'KCA-2026-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'created_by_user_id' => $admin->getKey(),
            ]);
            $this->remember($enrollment);
            $assignment = KcaAssignment::factory()->inState(KcaAssignmentState::Assigned)->create([
                'kca_enrollment_id' => $enrollment->getKey(),
                'kca_module_id' => $modules[0]->getKey(),
                'title' => 'Foundations reflection',
            ]);
            $this->remember($assignment);
            $lesson = KcaLesson::query()->where('kca_module_id', $modules[0]->getKey())->orderBy('sequence')->first();
            if ($lesson !== null) {
                $attendance = KcaAttendance::factory()->create([
                    'kca_enrollment_id' => $enrollment->getKey(),
                    'kca_lesson_id' => $lesson->getKey(),
                    'status' => KcaAttendanceStatus::Present,
                    'recorded_by_user_id' => $admin->getKey(),
                    'session_on' => now()->subWeek()->toDateString(),
                ]);
                $this->remember($attendance);
            }
            if ($index === 0) {
                $code = 'FHC-KCA-DEMO-2026';
                $certificate = KcaCertificate::factory()->create([
                    'kca_enrollment_id' => $enrollment->getKey(),
                    'person_id' => $person->getKey(),
                    'certificate_number' => 'KCA-CERT-DEMO-001',
                    'verification_code_hash' => $this->certificateHasher->hash($code),
                    'issued_by_user_id' => $admin->getKey(),
                    'issuance_key_hash' => hash('sha256', 'demo-cert-1'),
                ]);
                $this->remember($certificate);
            }
        }
    }

    /** @param  array<string, array{0: int, 1: int, 2: int}>  $palette */
    /**
     * @param  list<Person>  $authors
     */
    private function press(array $palette, array $authors): void
    {
        $books = [
            ['Kingdom Leadership', 'A practical guide for leading with humility and spiritual authority.', 'Spiritual Growth', $palette['violet']],
            ['Walking in Purpose', 'Discover God’s calling for your life and walk it out daily.', 'Christian Living', $palette['gold']],
            ['The Power of Prayer', 'Devotional readings to strengthen your prayer life.', 'Prayer', $palette['green']],
            ['The Heart of Worship', 'Returning to a life of wholehearted worship.', 'Worship', $palette['navy']],
            ['Foundations of Faith', 'Doctrine that forms resilient disciples.', 'Doctrine', $palette['teal']],
            ['Living by the Spirit', 'Daily dependence on the Holy Spirit.', 'Christian Living', $palette['rose']],
        ];
        foreach ($books as $index => [$title, $description, $category, $color]) {
            $cover = $this->storePng(Str::slug($title).'-cover.png', $color);
            $publication = PressPublication::factory()->create([
                'title' => $title,
                'subtitle' => $description,
                'publisher_name' => 'Kingdom Press',
                'language_code' => 'en',
                'category' => $category,
                'description' => $description,
                'format' => PressPublicationFormat::Pdf,
                'availability' => PressPublicationAvailability::Available,
                'status' => PressPublicationStatus::Published,
                'cover_file_asset_id' => $cover->getKey(),
                'publication_date' => now()->subMonths(6 - $index)->toDateString(),
                'published_at' => now()->subMonths(6 - $index),
                'page_count' => 180 + $index * 12,
                'idempotency_key_hash' => hash('sha256', 'demo-press-'.$title),
                'request_fingerprint' => hash('sha256', 'demo-press-fp-'.$title),
            ]);
            $this->remember($publication);
            $this->attachMedia->handle($publication, $cover, MediaRole::Cover);

            $author = $authors[$index % count($authors)];
            $contributor = new PressPublicationContributor;
            $contributor->forceFill([
                'press_publication_id' => $publication->getKey(),
                'person_id' => $author->getKey(),
                'role' => PressContributorRole::Author,
            ])->save();
            $this->remember($contributor);
        }

        $manuscript = PressPublication::factory()->create([
            'title' => 'The Power of Covenant',
            'subtitle' => 'Manuscript in editorial review',
            'publisher_name' => 'Kingdom Press',
            'language_code' => 'en',
            'category' => 'Doctrine',
            'description' => 'Demo manuscript for workflow screens.',
            'format' => PressPublicationFormat::Print,
            'availability' => PressPublicationAvailability::Unavailable,
            'status' => PressPublicationStatus::EditorialReview,
            'idempotency_key_hash' => hash('sha256', 'demo-press-manuscript'),
            'request_fingerprint' => hash('sha256', 'demo-press-manuscript-fp'),
        ]);
        $this->remember($manuscript);
        $manuscriptAuthor = new PressPublicationContributor;
        $manuscriptAuthor->forceFill([
            'press_publication_id' => $manuscript->getKey(),
            'person_id' => ($authors[0] ?? null)?->getKey(),
            'role' => PressContributorRole::Author,
        ])->save();
        $this->remember($manuscriptAuthor);
    }

    /** @param  list<Person>  $people */
    private function pastoralCare(array $people): void
    {
        $prayers = [
            ['Healing for Mum', 'Please pray for complete recovery after surgery.'],
            ['New job in Lagos', 'Interview coming up — wisdom and favour.'],
            ['Peace in the home', 'Asking for unity and patience this season.'],
            ['KCA studies', 'Strength to finish assignments well.'],
            ['Mission trip', 'Provision and open doors for Accra.'],
            ['Salvation for siblings', 'That they would encounter Christ.'],
            ['Church planting', 'Wisdom as we host a new home church.'],
            ['Financial breakthrough', 'Trusting God for school fees.'],
        ];
        foreach ($prayers as $index => [$subject, $body]) {
            $row = new PrayerRequest;
            $row->forceFill([
                'person_id' => $people[$index % count($people)]->getKey(),
                'subject' => $subject,
                'body' => $body,
                'status' => $index > 5 ? 'answered' : 'open',
            ])->save();
            $this->remember($row);
        }
        $needs = [
            ['transport', 'Need a ride to Sunday service from Ikeja.'],
            ['food', 'Family of four needs a food parcel this week.'],
            ['counselling', 'Requesting pastoral counselling after loss.'],
            ['housing', 'Temporary accommodation while relocating to Lekki.'],
            ['medical', 'Support towards hospital bills.'],
            ['education', 'School supplies for two children.'],
        ];
        foreach ($needs as $index => [$category, $summary]) {
            $row = new PastoralNeed;
            $row->forceFill([
                'person_id' => $people[$index % count($people)]->getKey(),
                'category' => $category,
                'summary' => $summary,
                'status' => $index > 3 ? 'closed' : 'open',
            ])->save();
            $this->remember($row);
        }
    }

    /** @param  list<Person>  $people */
    private function finance(array $people): void
    {
        $purposes = ['tithe', 'offering', 'missions', 'projects', 'donation', 'kca', 'event_payment', 'publication'];
        $amounts = [5000000, 3000000, 10000000, 2500000, 2000000, 1500000, 7500000, 4500000];
        foreach ($amounts as $index => $amount) {
            $intent = PaymentIntent::factory()->create([
                'payer_person_id' => $people[$index % count($people)]->getKey(),
                'purpose_code' => $purposes[$index % count($purposes)],
                'amount_minor' => $amount,
                'currency' => 'NGN',
                'status' => PaymentIntentStatus::Succeeded,
                'succeeded_at' => now()->subDays($index),
                'idempotency_scope_hash' => hash('sha256', 'demo-pay-'.$index),
                'payload_fingerprint' => hash('sha256', 'demo-pay-fp-'.$index),
            ]);
            $this->remember($intent);
            $transaction = PaymentTransaction::factory()->create([
                'payment_intent_id' => $intent->getKey(),
                'provider_code' => ['local_manual', 'paystack', 'flutterwave'][$index % 3],
                'amount_minor' => $amount,
                'currency' => 'NGN',
                'occurred_at' => now()->subDays($index),
                'provider_event_hash' => hash('sha256', 'demo-txn-event-'.$index),
                'provider_reference_hash' => hash('sha256', 'demo-txn-ref-'.$index),
            ]);
            $this->remember($transaction);
            $receipt = PaymentReceipt::factory()->create([
                'payment_transaction_id' => $transaction->getKey(),
                'receipt_number' => 'FHC-RCP-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            ]);
            $this->remember($receipt);
            $reconciliation = new PaymentReconciliation;
            $reconciliation->forceFill([
                'payment_transaction_id' => $transaction->getKey(),
                'status' => $index === 7 ? PaymentReconciliationStatus::Mismatch : PaymentReconciliationStatus::Matched,
                'reason_code' => $index === 7 ? 'amount_or_currency_mismatch' : 'amount_currency_matched',
                'reconciled_at' => now()->subDays($index)->utc(),
            ])->save();
            $this->remember($reconciliation);
        }
    }

    private function communications(User $admin): void
    {
        $templates = [
            ['communications.template.sunday-reminder', 'Sunday Service Reminder', 'Join us this Sunday at 10:00 AM WAT.', CommunicationChannel::Email, 'communications.event_reminders'],
            ['communications.template.kca-open', 'KCA Admissions Open', 'Applications for the new KCA cohort are open.', CommunicationChannel::Email, 'communications.kca_updates'],
            ['communications.template.mission-update', 'Missions Update', 'See what God is doing across Lagos, Accra, and Nairobi.', CommunicationChannel::WhatsApp, 'communications.missions_updates'],
            ['communications.template.youth-summit', 'Youth Summit Invite', 'Register for Youth Summit 2026.', CommunicationChannel::Sms, 'communications.ministry_updates'],
        ];
        $templateModels = [];
        foreach ($templates as [$code, $subject, $body, $channel, $purpose]) {
            $template = CommunicationTemplate::factory()->create([
                'code' => $code,
                'subject' => $subject,
                'body' => $body,
                'channel' => $channel,
                'created_by_user_id' => $admin->getKey(),
            ]);
            $this->remember($template);
            $templateModels[] = [$template, $channel, $purpose];
        }
        $audience = CommunicationAudience::factory()->create([
            'code' => 'audience.demo.members',
            'name' => 'All Family House members',
            'created_by_user_id' => $admin->getKey(),
        ]);
        $this->remember($audience);
        $deliveryStatuses = [
            CommunicationDeliveryStatus::Succeeded,
            CommunicationDeliveryStatus::Succeeded,
            CommunicationDeliveryStatus::Failed,
            CommunicationDeliveryStatus::Pending,
        ];
        foreach ($templateModels as $index => [$template, $channel, $purpose]) {
            $broadcast = CommunicationBroadcast::factory()->create([
                'communication_template_id' => $template->getKey(),
                'communication_audience_id' => $audience->getKey(),
                'kind' => CommunicationKind::Broadcast,
                'channel' => $channel,
                'purpose' => $purpose,
                'status' => $index === 0 ? CommunicationBroadcastStatus::Draft : CommunicationBroadcastStatus::Prepared,
                'prepared_at' => $index === 0 ? null : now()->subDays($index)->utc(),
                'created_by_user_id' => $admin->getKey(),
                'idempotency_key_hash' => hash('sha256', 'demo-broadcast-'.$index),
            ]);
            $this->remember($broadcast);

            $recipientUser = User::factory()->withPerson()->create();
            $this->remember($recipientUser);
            $recipient = CommunicationRecipient::factory()->create([
                'communication_broadcast_id' => $broadcast->getKey(),
                'user_id' => $recipientUser->getKey(),
                'person_id' => $recipientUser->person_id,
            ]);
            $this->remember($recipient);

            $status = $deliveryStatuses[$index];
            $attempt = CommunicationDeliveryAttempt::factory()->create([
                'communication_recipient_id' => $recipient->getKey(),
                'channel' => $channel,
                'status' => $status,
                'result_code' => match ($status) {
                    CommunicationDeliveryStatus::Succeeded => 'accepted',
                    CommunicationDeliveryStatus::Failed => 'provider_rejected',
                    default => 'queued',
                },
                'attempted_at' => now()->subHours($index + 1),
            ]);
            $this->remember($attempt);
        }
    }

    private function alerts(User $admin): void
    {
        foreach ([
            ['alerts.demo.follow-up-overdue', 'Follow-up overdue', 'first_timer.follow_up_overdue'],
            ['alerts.demo.large-gift', 'Large gift received', 'finance.large_gift'],
        ] as [$code, $title, $condition]) {
            $rule = AlertRule::factory()->active()->create([
                'code' => $code,
                'title' => $title,
                'condition_type' => $condition,
                'severity' => AlertSeverity::Warning,
                'created_by_user_id' => $admin->getKey(),
                'updated_by_user_id' => $admin->getKey(),
            ]);
            $this->remember($rule);
        }
    }

    /** @param  array<string, array{0: int, 1: int, 2: int}>  $palette */
    private function attachContentImages(array $palette): void
    {
        $colors = array_values($palette);
        ContentPage::query()->orderBy('slug')->get()->each(function (ContentPage $page) use ($colors): void {
            $this->remember($page);
            $hero = $this->storePng($page->slug.'-hero.png', $colors[crc32($page->slug) % count($colors)]);
            $this->attachMedia->handle($page, $hero, MediaRole::Hero);
            $page->items()->orderBy('sort_order')->get()->each(function (ContentItem $item, int $index) use ($colors): void {
                $this->remember($item);
                if (! in_array($item->kind, ['card', 'sermon', 'partner', 'pillar', 'story', 'project'], true)) {
                    return;
                }
                $asset = $this->storePng(Str::slug($item->title).'.png', $colors[$index % count($colors)]);
                $this->attachMedia->handle($item, $asset, MediaRole::Cover);
            });
        });
    }

    /**
     * @param  list<Person>  $people
     * @param  array<string, array{0: int, 1: int, 2: int}>  $palette
     */
    private function attachPeopleAvatars(array $people, array $palette): void
    {
        $colors = array_values($palette);
        foreach ($people as $index => $person) {
            $asset = $this->storePng('avatar-'.$person->public_id.'.png', $colors[$index % count($colors)], 240, 240);
            $this->attachMedia->handle($person, $asset, MediaRole::Avatar);
        }
    }

    private function namedUser(string $given, string $family, string $email, string $displayName): User
    {
        $person = $this->namedPerson($given, $family);
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $user = User::factory()->create([
                'name' => $displayName,
                'email' => $email,
                'password' => self::PASSWORD,
                'person_id' => $person->getKey(),
                'email_verified_at' => now(),
            ]);
        } else {
            $user->forceFill([
                'password' => self::PASSWORD,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }
        $this->remember($user);

        return $user;
    }

    private function namedPerson(string $given, string $family): Person
    {
        $existing = PersonProfile::query()
            ->where('given_name', $given)
            ->where('family_name', $family)
            ->first();
        if ($existing !== null) {
            $person = Person::query()->findOrFail($existing->person_id);
            $this->remember($person);
            $this->remember($existing);

            return $person;
        }

        $person = Person::factory()->create();
        $profile = PersonProfile::factory()->create([
            'person_id' => $person->getKey(),
            'given_name' => $given,
            'family_name' => $family,
            'preferred_name' => $given,
        ]);
        $this->remember($person);
        $this->remember($profile);

        return $person;
    }

    private function grantAllAdminRoles(User $user): void
    {
        $scope = new ScopeReference('global', 'platform');
        $role = Role::query()->where('code', AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE)->first();
        if ($role === null) {
            $this->grantRoles($user, array_keys(AuthorizationBundleCatalog::BUNDLES));

            return;
        }

        $assignment = $this->assignRole->handle($user, $role);
        $this->assignScope->handle($assignment, $scope);
    }

    private function grantChurchAdministrator(User $user, Church $church): void
    {
        $role = Role::query()
            ->where('code', AuthorizationBundleCatalog::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE)
            ->firstOrFail();
        $assignment = $this->assignRole->handle($user, $role);
        $this->remember($assignment);
        $scopeAssignment = $this->assignScope->handle($assignment, new ScopeReference('church', $church->public_id));
        $this->remember($scopeAssignment);
    }

    private function ensureMembership(Person $person, Church $church): void
    {
        $existing = ChurchMembership::query()
            ->where('person_id', $person->getKey())
            ->where('church_id', $church->getKey())
            ->where('active_marker', 1)
            ->exists();
        if ($existing) {
            return;
        }

        $membership = ChurchMembership::factory()->create([
            'person_id' => $person->getKey(),
            'church_id' => $church->getKey(),
            'home_church_id' => null,
            'joined_at' => now()->subMonths(6),
        ]);
        $this->remember($membership);
    }

    /** @param  list<string>  $codes */
    private function grantRoles(User $user, array $codes): void
    {
        $scope = new ScopeReference('global', 'platform');
        foreach ($codes as $code) {
            $role = Role::query()->where('code', $code)->firstOrFail();
            $assignment = $this->assignRole->handle($user, $role);
            $this->remember($assignment);
            $scopeAssignment = $this->assignScope->handle($assignment, $scope);
            $this->remember($scopeAssignment);
        }
    }

    private function country(string $iso, string $name): Country
    {
        $country = Country::query()->firstOrCreate(['iso_code' => $iso], ['name' => $name]);
        $this->rememberIfNew($country);

        return $country;
    }

    private function level(Country $country, string $code, string $name, int $order): AdministrativeLevel
    {
        $level = AdministrativeLevel::query()->firstOrCreate(
            ['country_id' => $country->getKey(), 'code' => $code],
            ['name' => $name, 'sort_order' => $order],
        );
        $this->rememberIfNew($level);

        return $level;
    }

    private function unit(
        Country $country,
        AdministrativeLevel $level,
        string $name,
        string $reference,
        ?AdministrativeUnit $parent = null,
    ): AdministrativeUnit {
        $existing = AdministrativeUnit::query()
            ->whereBelongsTo($country)
            ->where('administrative_level_id', $level->getKey())
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when(
                $parent !== null,
                static fn ($query) => $query->where('parent_id', $parent->getKey()),
                static fn ($query) => $query->whereNull('parent_id'),
            )
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $unit = AdministrativeUnit::query()->firstOrCreate(
            ['country_id' => $country->getKey(), 'reference_code' => $reference],
            [
                'administrative_level_id' => $level->getKey(),
                'parent_id' => $parent?->getKey(),
                'name' => $name,
            ],
        );
        $this->rememberIfNew($unit);

        return $unit;
    }

    private function location(
        Country $country,
        AdministrativeUnit $unit,
        string $name,
        string $address,
        string $locality,
        string $timezone,
        float $lat,
        float $lng,
    ): Location {
        $location = Location::query()->firstOrNew(['name' => $name, 'country_id' => $country->getKey()]);
        $location->forceFill([
            'administrative_unit_id' => $unit->getKey(),
            'address_line_one' => $address,
            'locality' => $locality,
            'timezone' => $timezone,
            'latitude' => $lat,
            'longitude' => $lng,
        ])->save();
        $this->rememberIfNew($location);

        return $location;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function storePng(string $filename, array $rgb, int $width = 640, int $height = 360): FileAsset
    {
        $bytes = DemoPngFactory::make($rgb, $width, $height);
        $objectKey = 'assets/demo/'.Str::ulid().'-'.Str::slug(pathinfo($filename, PATHINFO_FILENAME)).'.png';
        Storage::disk('local')->put($objectKey, $bytes);

        $asset = new FileAsset;
        $asset->forceFill([
            'purpose' => 'media.public',
            'classification' => FileAssetClassification::Public,
            'storage_provider' => StorageProvider::Local,
            'disk_name' => 'local',
            'object_key' => $objectKey,
            'metadata' => ['original_filename' => $filename],
            'detected_mime_type' => 'image/png',
            'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'idempotency_key_hash' => hash('sha256', $objectKey),
            'idempotency_scope_hash' => hash('sha256', 'demo|'.$objectKey),
            'status' => FileAssetStatus::Available,
            'malware_scan_status' => MalwareScanStatus::Clean,
            'malware_scanned_at' => now()->utc(),
            'available_at' => now()->utc(),
        ])->save();
        $this->remember($asset);

        return $asset;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function attachImage(Model $model, MediaRole $role, array $rgb, string $filename): void
    {
        if (! method_exists($model, 'mediaAttachments')) {
            return;
        }
        $asset = $this->storePng($filename, $rgb);
        $attachment = $this->attachMedia->handle($model, $asset, $role);
        $this->remember($attachment);
    }

    private function remember(Model $model): void
    {
        $this->registrar->remember($model);
    }

    private function rememberIfNew(Model $model): void
    {
        if ($model->wasRecentlyCreated) {
            $this->remember($model);
        }
    }
}

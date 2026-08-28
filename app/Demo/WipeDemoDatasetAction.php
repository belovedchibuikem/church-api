<?php

namespace App\Demo;

use App\Models\DemoDataset;
use App\Models\DemoDatasetRecord;
use App\Models\FileAsset;
use App\Models\User;
use App\Storage\StorageProvider;
use Database\Seeders\ContentPagesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class WipeDemoDatasetAction
{
    /**
     * @return array{wiped: bool, preserved_user_id: ?string, restored_cms: bool, deleted_records: int}
     */
    public function handle(?User $actor = null): array
    {
        $dataset = DemoDataset::query()->where('dataset_key', DemoDataset::KEY)->first();
        if ($dataset === null) {
            return [
                'wiped' => false,
                'preserved_user_id' => $actor?->public_id,
                'restored_cms' => false,
                'deleted_records' => 0,
            ];
        }

        $records = DemoDatasetRecord::query()
            ->where('dataset_key', DemoDataset::KEY)
            ->get()
            ->groupBy('table_name');

        $preserveUserId = $actor?->getKey();
        $preservePersonId = $actor?->person_id;
        $deleted = 0;

        $fileIds = collect($records->get('file_assets', []))->pluck('record_id')->all();
        if ($fileIds !== []) {
            FileAsset::query()->whereIn('id', $fileIds)->get()->each(function (FileAsset $asset): void {
                if ($asset->storage_provider === StorageProvider::Local) {
                    Storage::disk($asset->disk_name ?: 'local')->delete($asset->object_key);
                }
            });
        }

        $userIds = collect($records->get('users', []))->pluck('record_id')->reject(
            fn (mixed $id): bool => $preserveUserId !== null && (int) $id === (int) $preserveUserId,
        )->values()->all();
        $personIds = collect($records->get('people', []))->pluck('record_id')->reject(
            fn (mixed $id): bool => $preservePersonId !== null && (int) $id === (int) $preservePersonId,
        )->values()->all();

        DB::transaction(function () use ($records, $userIds, $personIds, &$deleted): void {
            if ($userIds !== []) {
                $methodIds = Schema::hasTable('mfa_methods')
                    ? DB::table('mfa_methods')->whereIn('user_id', $userIds)->pluck('id')->all()
                    : [];
                $this->deleteOwnedRows('audit_events', 'actor_user_id', $userIds);
                $this->deleteOwnedRows('access_decisions', 'actor_user_id', $userIds);
                $this->deleteOwnedRows('security_sessions', 'user_id', $userIds);
                $this->deleteOwnedRows('devices', 'user_id', $userIds);
                $this->deleteOwnedRows('mfa_recovery_codes', 'mfa_method_id', $methodIds);
                $this->deleteOwnedRows('mfa_methods', 'user_id', $userIds);
                $this->deleteOwnedRows('sessions', 'user_id', $userIds);
            }

            $this->withForeignKeyChecksDisabled(function () use ($records, $userIds, $personIds, &$deleted): void {
                foreach ($this->deleteOrder() as $table) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }
                    $ids = collect($records->get($table, []))->pluck('record_id');
                    if (in_array($table, ['users'], true) && $userIds !== []) {
                        $ids = collect($userIds);
                    }
                    if (in_array($table, ['people', 'person_profiles'], true) && $personIds !== []) {
                        if ($table === 'people') {
                            $ids = collect($personIds);
                        } else {
                            $ids = collect($records->get($table, []))->pluck('record_id')->filter(function (mixed $id) use ($personIds): bool {
                                $personId = DB::table('person_profiles')->where('id', $id)->value('person_id');

                                return in_array((int) $personId, array_map('intval', $personIds), true);
                            });
                        }
                    }

                    $ids = $ids->unique()->filter()->values()->all();
                    if ($ids === []) {
                        continue;
                    }

                    $deleted += DB::table($table)->whereIn('id', $ids)->delete();
                }
            });

            DemoDatasetRecord::query()->where('dataset_key', DemoDataset::KEY)->delete();
            DemoDataset::query()->where('dataset_key', DemoDataset::KEY)->delete();
        }, attempts: 3);

        (new ContentPagesSeeder)->run();

        return [
            'wiped' => true,
            'preserved_user_id' => $actor?->public_id,
            'restored_cms' => true,
            'deleted_records' => $deleted,
        ];
    }

    /**
     * @param  list<mixed>  $ids
     */
    private function deleteOwnedRows(string $table, string $column, array $ids): void
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $ids)->delete();
    }

    private function withForeignKeyChecksDisabled(callable $callback): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            $callback();
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }

    /** @return list<string> */
    private function deleteOrder(): array
    {
        return [
            'media_attachments',
            'communication_delivery_attempts',
            'communication_notifications',
            'communication_recipients',
            'communication_broadcasts',
            'communication_audience_rules',
            'communication_audiences',
            'communication_templates',
            'payment_disputes',
            'payment_refunds',
            'payment_receipts',
            'payment_reconciliations',
            'payment_transactions',
            'payment_intents',
            'event_feedback',
            'event_attendances',
            'event_registrations',
            'kca_certificates',
            'kca_assessment_results',
            'kca_evidence_reviews',
            'kca_evidence_submissions',
            'kca_assignments',
            'kca_attendances',
            'kca_lecturer_assignments',
            'kca_mentor_assignments',
            'kca_module_prerequisites',
            'kca_lessons',
            'kca_enrollments',
            'kca_admission_decisions',
            'kca_applications',
            'kca_modules',
            'kca_cohorts',
            'kca_years',
            'follow_up_interactions',
            'mentor_assignments',
            'mission_team_assignments',
            'mission_invitations',
            'mission_soul_journeys',
            'follow_up_tasks',
            'first_timers',
            'church_memberships',
            'home_church_application_transitions',
            'home_church_applications',
            'home_churches',
            'prayer_requests',
            'pastoral_needs',
            'messages',
            'message_conversation_participants',
            'message_conversations',
            'alert_occurrences',
            'alert_rules',
            'press_translation_transitions',
            'press_translations',
            'press_publication_transitions',
            'press_publication_contributors',
            'press_publications',
            'ministry_events',
            'crusades',
            'churches',
            'locations',
            'administrative_units',
            'administrative_levels',
            'countries',
            'content_items',
            'content_pages',
            'file_assets',
            'scope_assignments',
            'role_assignments',
            'person_consents',
            'person_preferences',
            'person_profiles',
            'users',
            'people',
        ];
    }
}

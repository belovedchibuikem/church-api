<?php

namespace App\Support\Kca;

use App\Models\KcaApplication;
use App\Models\KcaLeadershipRecommendation;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RequestKcaLeadershipRecommendationAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @return array{recommendation: KcaLeadershipRecommendation, token: string|null}
     */
    public function handle(
        KcaApplication $application,
        string $name,
        string $email,
        ?string $role = null,
        ?string $phone = null,
        ?User $actor = null,
    ): array {
        $normalizedName = Str::squish($name);
        $normalizedEmail = Str::lower(Str::squish($email));
        if ($normalizedName === '' || $normalizedEmail === '' || ! filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A recommender name and valid email are required.');
        }

        return DB::transaction(function () use ($application, $normalizedName, $normalizedEmail, $role, $phone, $actor): array {
            $existing = KcaLeadershipRecommendation::query()
                ->where('kca_application_id', $application->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null && in_array($existing->status, ['submitted', 'verified'], true)) {
                return ['recommendation' => $existing, 'token' => null];
            }

            $plain = $existing && $existing->recommender_email === $normalizedEmail
                ? null
                : bin2hex(random_bytes(32));

            $row = $existing ?? new KcaLeadershipRecommendation;
            $row->forceFill([
                'kca_application_id' => $application->getKey(),
                'recommender_name' => $normalizedName,
                'recommender_email' => $normalizedEmail,
                'recommender_role' => $role ? Str::squish($role) : null,
                'recommender_phone' => $phone ? Str::squish($phone) : null,
                'status' => 'requested',
            ]);
            if ($plain !== null) {
                $row->token_hash = hash('sha256', $plain);
            }
            $row->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.recommendation.requested',
                actor: $actor,
                targetType: 'kca_leadership_recommendation',
                targetId: $row->public_id,
                metadata: [
                    'application_id' => $application->public_id,
                    'recommender_email' => $normalizedEmail,
                ],
            ));

            return ['recommendation' => $row, 'token' => $plain];
        }, attempts: 3);
    }
}

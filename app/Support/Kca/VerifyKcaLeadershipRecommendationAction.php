<?php

namespace App\Support\Kca;

use App\Models\KcaLeadershipRecommendation;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class VerifyKcaLeadershipRecommendationAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(KcaLeadershipRecommendation $recommendation, User $actor): KcaLeadershipRecommendation
    {
        return DB::transaction(function () use ($recommendation, $actor): KcaLeadershipRecommendation {
            $row = KcaLeadershipRecommendation::query()->lockForUpdate()->findOrFail($recommendation->getKey());
            if ($row->status === 'verified') {
                return $row;
            }
            if ($row->status !== 'submitted') {
                throw new ConflictHttpException('Only a submitted recommendation can be verified.');
            }

            $row->forceFill([
                'status' => 'verified',
                'verified_at' => now()->utc(),
                'verified_by_user_id' => $actor->getKey(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.recommendation.verified',
                actor: $actor,
                targetType: 'kca_leadership_recommendation',
                targetId: $row->public_id,
                metadata: [
                    'application_id' => $row->application?->public_id,
                ],
            ));

            return $row;
        }, attempts: 3);
    }
}

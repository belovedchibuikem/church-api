<?php

namespace App\Support\Kca;

use App\Models\KcaApplication;
use App\Models\KcaLeadershipRecommendation;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        if ($normalizedName === '') {
            $normalizedName = 'Church leader';
        }
        $normalizedEmail = Str::lower(Str::squish($email));
        if ($normalizedEmail === '' || ! filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid church leader email is required.');
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

            if ($plain !== null) {
                $this->sendLeaderEmail($application, $row, $plain);
            }

            return ['recommendation' => $row, 'token' => $plain];
        }, attempts: 3);
    }

    private function sendLeaderEmail(KcaApplication $application, KcaLeadershipRecommendation $row, string $token): void
    {
        $application->loadMissing('person.profile', 'person.user');
        $applicant = PersonDisplayName::of($application->person) ?: 'a KCA applicant';
        $frontend = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $link = $frontend.'/kca/recommend/'.$token;
        $body = <<<TEXT
Kingdom Change Agents (KCA) is asking you to recommend {$applicant}.

{$applicant} gave this email address so their church leader can complete the recommendation. The student does not write this recommendation. Please open the link, add your name and role, and send a short note about the applicant.

{$link}

This message is from KCA at Family House Connect.
TEXT;

        try {
            Mail::raw($body, function ($message) use ($row, $applicant): void {
                $message->to($row->recommender_email)->subject('KCA leadership recommendation for '.$applicant);
            });
        } catch (\Throwable $exception) {
            Log::warning('KCA leadership recommendation email could not be sent.', [
                'recommendation_id' => $row->public_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

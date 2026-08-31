<?php

namespace App\Support\Kca;

use App\Models\KcaLeadershipRecommendation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmitKcaLeadershipRecommendationAction
{
    public function handle(string $token, string $statement): KcaLeadershipRecommendation
    {
        $normalizedToken = strtolower(trim($token));
        $statement = Str::squish($statement);
        if (! preg_match('/\A[a-f0-9]{64}\z/', $normalizedToken)) {
            throw new AccessDeniedHttpException('This recommendation link is not valid.');
        }
        if ($statement === '' || Str::length($statement) > 5000) {
            throw new InvalidArgumentException('A recommendation statement of 1 to 5000 characters is required.');
        }

        return DB::transaction(function () use ($normalizedToken, $statement): KcaLeadershipRecommendation {
            $row = KcaLeadershipRecommendation::query()
                ->where('token_hash', hash('sha256', $normalizedToken))
                ->lockForUpdate()
                ->first();
            if ($row === null) {
                throw new AccessDeniedHttpException('This recommendation link is not valid.');
            }
            if ($row->status === 'verified') {
                throw new ConflictHttpException('This recommendation has already been verified.');
            }
            if ($row->status === 'submitted') {
                return $row;
            }

            $row->forceFill([
                'statement' => $statement,
                'status' => 'submitted',
                'submitted_at' => now()->utc(),
            ])->save();

            return $row;
        }, attempts: 3);
    }
}

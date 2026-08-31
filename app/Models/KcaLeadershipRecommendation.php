<?php

namespace App\Models;

use Database\Factories\KcaLeadershipRecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kca_application_id',
    'recommender_name',
    'recommender_email',
    'recommender_role',
    'recommender_phone',
    'token_hash',
    'status',
    'statement',
    'submitted_at',
    'verified_at',
    'verified_by_user_id',
])]
#[Hidden(['token_hash'])]
class KcaLeadershipRecommendation extends Model
{
    /** @use HasFactory<KcaLeadershipRecommendationFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(KcaApplication::class, 'kca_application_id');
    }

    protected function casts(): array
    {
        return [
            'submitted_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
        ];
    }
}

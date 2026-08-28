<?php

namespace App\Models;

use App\Kca\KcaApplicationState;
use Database\Factories\KcaAdmissionDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class KcaAdmissionDecision extends Model
{
    /** @use HasFactory<KcaAdmissionDecisionFactory> */
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

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    protected function casts(): array
    {
        return ['outcome' => KcaApplicationState::class, 'decided_at' => 'immutable_datetime'];
    }
}

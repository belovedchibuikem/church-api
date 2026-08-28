<?php

namespace App\Models;

use App\Communication\CommunicationAudienceRuleType;
use Database\Factories\CommunicationAudienceRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class CommunicationAudienceRule extends Model
{
    /** @use HasFactory<CommunicationAudienceRuleFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(CommunicationAudience::class, 'communication_audience_id');
    }

    protected function casts(): array
    {
        return ['type' => CommunicationAudienceRuleType::class];
    }
}

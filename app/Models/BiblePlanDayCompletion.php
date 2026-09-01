<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class BiblePlanDayCompletion extends Model
{
    public $timestamps = false;

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(BiblePlanEnrollment::class, 'enrollment_id');
    }

    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
            'day_number' => 'integer',
        ];
    }
}

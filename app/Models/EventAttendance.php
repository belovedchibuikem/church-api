<?php

namespace App\Models;

use App\Exceptions\FinancialRecordImmutableException;
use Database\Factories\EventAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class EventAttendance extends Model
{
    /** @use HasFactory<EventAttendanceFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    protected function casts(): array
    {
        return ['attended_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new FinancialRecordImmutableException);
        static::deleting(fn (): never => throw new FinancialRecordImmutableException);
    }
}

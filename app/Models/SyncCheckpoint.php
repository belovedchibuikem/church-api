<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['person_id', 'cursor', 'updated_at'])]
class SyncCheckpoint extends Model
{
    public $timestamps = false;

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    protected function casts(): array
    {
        return [
            'updated_at' => 'immutable_datetime',
        ];
    }
}

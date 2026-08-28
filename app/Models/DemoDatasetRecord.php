<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([])]
class DemoDatasetRecord extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}

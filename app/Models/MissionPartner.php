<?php

namespace App\Models;

use Database\Factories\MissionPartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'partner_type', 'status', 'geography', 'contact_name', 'contact_email', 'notes', 'archived_at'])]
class MissionPartner extends Model
{
    /** @use HasFactory<MissionPartnerFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return [
            'archived_at' => 'immutable_datetime',
        ];
    }
}

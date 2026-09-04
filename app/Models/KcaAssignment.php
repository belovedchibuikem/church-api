<?php

namespace App\Models;

use App\Casts\KcaAssignmentStateCast;
use App\Kca\KcaAssignmentState;
use Database\Factories\KcaAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kca_enrollment_id',
    'kca_module_id',
    'kca_lesson_id',
    'title',
    'assignment_kind',
    'soul_tree_spec',
    'state',
    'due_at',
    'assigned_at',
    'last_transitioned_by_user_id',
])]
class KcaAssignment extends Model
{
    /** @use HasFactory<KcaAssignmentFactory> */
    use HasFactory, HasUlids;

    protected $attributes = ['state' => KcaAssignmentState::Draft->value];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(KcaEnrollment::class, 'kca_enrollment_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(KcaModule::class, 'kca_module_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(KcaLesson::class, 'kca_lesson_id');
    }

    public function lastTransitionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_transitioned_by_user_id');
    }

    public function evidenceSubmissions(): HasMany
    {
        return $this->hasMany(KcaEvidenceSubmission::class);
    }

    public function soulWins(): HasMany
    {
        return $this->hasMany(KcaSoulWin::class);
    }

    public function isSoulWinning(): bool
    {
        return ($this->assignment_kind ?? 'standard') === 'soul_winning';
    }

    protected function casts(): array
    {
        return [
            'state' => KcaAssignmentStateCast::class,
            'soul_tree_spec' => 'array',
            'due_at' => 'immutable_datetime',
            'assigned_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'mentor_reviewed_at' => 'immutable_datetime',
            'admin_reviewed_at' => 'immutable_datetime',
            'final_assessed_at' => 'immutable_datetime',
        ];
    }
}

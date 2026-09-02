<?php

namespace App\Models;

use Database\Factories\KcaGovernanceConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pass_threshold_percent',
    'attendance_threshold_percent',
    'require_final_assessment',
    'require_signed_pdf',
    'certificate_signer_name',
    'certificate_signer_title',
    'admission_signer_name',
    'admission_signer_title',
    'admission_letterhead_file_asset_id',
    'admission_signature_file_asset_id',
    'admission_reference_prefix',
    'admission_letter_body_template',
    'admission_programme_commencement',
    'admission_programme_completion',
    'admission_programme_venue',
    'admission_programme_schedule',
    'admission_programme_mentor',
])]
class KcaGovernanceConfiguration extends Model
{
    /** @use HasFactory<KcaGovernanceConfigurationFactory> */
    use HasFactory;

    protected $attributes = [
        'pass_threshold_percent' => 70,
        'attendance_threshold_percent' => 75,
        'require_final_assessment' => true,
        'require_signed_pdf' => false,
        'is_active' => true,
        'configuration_revision' => 1,
    ];

    public function admissionLetterheadFile(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'admission_letterhead_file_asset_id');
    }

    public function admissionSignatureFile(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'admission_signature_file_asset_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pass_threshold_percent' => 'integer',
            'attendance_threshold_percent' => 'integer',
            'require_final_assessment' => 'boolean',
            'require_signed_pdf' => 'boolean',
            'is_active' => 'boolean',
            'configuration_revision' => 'integer',
        ];
    }
}

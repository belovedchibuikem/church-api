<?php

namespace App\Models;

use App\Storage\ObjectStorageDriver;
use App\Storage\ObjectStorageValidationStatus;
use Database\Factories\ObjectStorageConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'driver',
    'access_key_id',
    'secret_access_key',
    'region',
    'bucket',
    'endpoint',
    'url',
    'root_prefix',
    'use_path_style_endpoint',
])]
#[Hidden(['access_key_id', 'secret_access_key'])]
class ObjectStorageConfiguration extends Model
{
    /** @use HasFactory<ObjectStorageConfigurationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'driver' => ObjectStorageDriver::S3->value,
        'use_path_style_endpoint' => false,
        'is_active' => false,
        'configuration_revision' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => ObjectStorageDriver::class,
            'access_key_id' => 'encrypted',
            'secret_access_key' => 'encrypted',
            'use_path_style_endpoint' => 'boolean',
            'is_active' => 'boolean',
            'configuration_revision' => 'integer',
            'last_validation_status' => ObjectStorageValidationStatus::class,
            'last_validation_attempted_at' => 'datetime',
            'validated_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }
}

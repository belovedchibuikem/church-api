<?php

namespace Database\Factories;

use App\Files\FileAssetClassification;
use App\Files\FileAssetStatus;
use App\Files\MalwareScanStatus;
use App\Models\FileAsset;
use App\Storage\StorageProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FileAsset>
 */
class FileAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_person_id' => null,
            'purpose' => 'document.general',
            'classification' => FileAssetClassification::Internal,
            'storage_provider' => StorageProvider::Local,
            'disk_name' => 'local',
            'storage_configuration_revision' => null,
            'object_key' => 'assets/'.Str::ulid().'-'.Str::random(32),
            'metadata' => ['original_filename' => 'document.txt'],
            'detected_mime_type' => 'text/plain',
            'byte_size' => 12,
            'sha256' => hash('sha256', Str::random()),
            'idempotency_key_hash' => hash('sha256', Str::uuid()->toString()),
            'idempotency_scope_hash' => hash('sha256', Str::uuid()->toString()),
            'status' => FileAssetStatus::Quarantined,
            'malware_scan_status' => MalwareScanStatus::Pending,
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FileAssetStatus::Available,
            'malware_scan_status' => MalwareScanStatus::Clean,
            'malware_scanned_at' => now(),
            'available_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FileAssetStatus::Rejected,
            'malware_scan_status' => MalwareScanStatus::Infected,
            'malware_scanned_at' => now(),
            'rejection_reason' => 'malware_detected',
            'rejected_at' => now(),
        ]);
    }
}

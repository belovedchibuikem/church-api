<?php

namespace Database\Factories;

use App\Models\FileAsset;
use App\Models\PressPublication;
use App\Models\PressPublicationAsset;
use App\Press\PressAssetFormat;
use App\Press\PressAssetProcessingStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PressPublicationAsset>
 */
class PressPublicationAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'press_publication_id' => PressPublication::factory(),
            'file_asset_id' => FileAsset::factory(),
            'asset_format' => PressAssetFormat::Pdf,
            'processing_status' => PressAssetProcessingStatus::Ready,
            'version' => 1,
            'is_current' => true,
            'is_required' => false,
        ];
    }
}

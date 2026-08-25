<?php

namespace App\Storage\Actions;

use App\Models\ObjectStorageConfiguration;
use Illuminate\Support\Facades\DB;

class ActivateLocalStorageAction
{
    public function handle(): void
    {
        DB::transaction(function (): void {
            ObjectStorageConfiguration::query()
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->each(function (ObjectStorageConfiguration $configuration): void {
                    $configuration->forceFill([
                        'is_active' => false,
                        'activated_at' => null,
                    ])->save();
                });
        }, attempts: 3);
    }
}

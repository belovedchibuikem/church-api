<?php

namespace App\Demo;

use App\Models\DemoDataset;
use App\Models\DemoDatasetRecord;
use Illuminate\Database\Eloquent\Model;

class DemoDatasetRegistrar
{
    public function remember(Model $model): Model
    {
        $exists = DemoDatasetRecord::query()
            ->where('dataset_key', DemoDataset::KEY)
            ->where('table_name', $model->getTable())
            ->where('record_id', $model->getKey())
            ->exists();

        if (! $exists) {
            $record = new DemoDatasetRecord;
            $record->forceFill([
                'dataset_key' => DemoDataset::KEY,
                'table_name' => $model->getTable(),
                'record_id' => $model->getKey(),
                'public_id' => $model->getAttribute('public_id'),
                'created_at' => now()->utc(),
            ])->save();
        }

        return $model;
    }
}

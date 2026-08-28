<?php

namespace Database\Seeders;

use App\Demo\SeedDemoDatasetAction;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(SeedDemoDatasetAction::class)->handle();

        if ($this->command !== null) {
            if ($result['skipped']) {
                $this->command->info('Demo dataset already present. Use php artisan demo:seed --force to replace it.');
            } else {
                $this->command->info('Demo dataset seeded for Family House Connect.');
                $this->command->info('Admin login: admin@familyhouse.demo / DemoPass!2026');
            }
        }
    }
}

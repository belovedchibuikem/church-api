<?php

namespace Database\Seeders;

use App\Support\Organization\SeedWorldGeographyAction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (is_file(database_path('data/geography/world-states.json'))) {
            app(SeedWorldGeographyAction::class)->handle(
                onlyIsos: ['NG', 'GH', 'KE'],
                withLocalities: true,
            );
        }

        // Production roles and account assignments are provisioned explicitly.
        $this->call([
            ContentPagesSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}

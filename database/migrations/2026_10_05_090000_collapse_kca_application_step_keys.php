<?php

use App\Models\KcaApplication;
use App\Support\Kca\KcaRegistrationProfile;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        KcaApplication::query()->whereNotNull('application_data')->chunkById(200, function ($applications): void {
            foreach ($applications as $application) {
                $data = $application->application_data;
                if (! is_array($data)) {
                    continue;
                }
                $normalized = KcaRegistrationProfile::normalizeIncoming($data);
                if ($normalized !== $data) {
                    $application->forceFill(['application_data' => $normalized])->saveQuietly();
                }
            }
        });
    }

    public function down(): void
    {
        // Step-prefixed copies duplicated the bare keys and are not restored.
    }
};

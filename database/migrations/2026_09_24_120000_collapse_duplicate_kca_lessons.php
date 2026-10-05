<?php

use App\Support\Kca\CollapseDuplicateKcaLessonsAction;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CollapseDuplicateKcaLessonsAction::class)->handle();
    }

    public function down(): void
    {
        // Duplicate lesson rows are removed. Restoring them would recreate the attendance split.
    }
};

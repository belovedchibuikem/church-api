<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('person_consents', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->string('purpose', 100);
            $table->string('policy_version', 100);
            $table->string('source', 100);
            $table->timestamp('granted_at');
            $table->string('withdrawal_source', 100)->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(
                ['person_id', 'purpose', 'withdrawn_at'],
                'person_consents_current_lookup',
            );
            $table->index(['purpose', 'policy_version']);
            $table->index('granted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_consents');
    }
};

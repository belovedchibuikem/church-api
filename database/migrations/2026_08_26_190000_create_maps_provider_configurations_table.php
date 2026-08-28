<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maps_provider_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('active_provider', 32)->default('leaflet');
            $table->text('google_api_key')->nullable();
            $table->text('mapbox_access_token')->nullable();
            $table->string('leaflet_tile_url')->nullable();
            $table->decimal('default_latitude', 10, 7)->default(6.5244000);
            $table->decimal('default_longitude', 10, 7)->default(3.3792000);
            $table->unsignedTinyInteger('default_zoom')->default(12);
            $table->boolean('is_active')->default(false)->index();
            $table->unsignedInteger('configuration_revision')->default(1);
            $table->string('last_validation_status', 32)->nullable();
            $table->string('last_validation_failure_code', 64)->nullable();
            $table->timestamp('last_validation_attempted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maps_provider_configurations');
    }
};

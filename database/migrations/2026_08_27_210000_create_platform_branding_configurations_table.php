<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_branding_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('app_name', 120)->default('Family House Connect');
            $table->foreignId('logo_file_asset_id')->nullable()->constrained('file_assets')->nullOnDelete();
            $table->foreignId('favicon_file_asset_id')->nullable()->constrained('file_assets')->nullOnDelete();
            $table->unsignedInteger('configuration_revision')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_branding_configurations');
    }
};

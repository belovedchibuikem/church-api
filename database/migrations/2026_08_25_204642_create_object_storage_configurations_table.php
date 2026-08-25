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
        Schema::create('object_storage_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 32)->unique();
            $table->text('access_key_id');
            $table->text('secret_access_key');
            $table->string('region', 64);
            $table->string('bucket');
            $table->string('endpoint')->nullable();
            $table->string('url')->nullable();
            $table->string('root_prefix')->nullable();
            $table->boolean('use_path_style_endpoint')->default(false);
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('object_storage_configurations');
    }
};

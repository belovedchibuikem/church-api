<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_provider_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('email_provider', 32)->default('none');
            $table->string('email_sender_name', 120)->nullable();
            $table->string('email_sender_address', 191)->nullable();
            $table->text('email_api_key')->nullable();
            $table->string('sms_provider', 32)->default('none');
            $table->string('sms_sender_id', 32)->nullable();
            $table->text('sms_api_key')->nullable();
            $table->text('sms_api_secret')->nullable();
            $table->string('whatsapp_provider', 32)->default('none');
            $table->string('whatsapp_phone_number_id', 64)->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->string('push_provider', 32)->default('none');
            $table->text('push_server_key')->nullable();
            $table->json('consent_required_channels');
            $table->unsignedTinyInteger('retry_max_attempts')->default(3);
            $table->unsignedInteger('retry_backoff_seconds')->default(60);
            $table->boolean('is_active')->default(false)->index();
            $table->unsignedInteger('configuration_revision')->default(1);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_provider_configurations');
    }
};

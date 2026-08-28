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
        Schema::create('payment_provider_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('active_provider', 32)->default('paystack');
            $table->text('paystack_secret_key')->nullable();
            $table->text('paystack_public_key')->nullable();
            $table->text('paystack_webhook_secret')->nullable();
            $table->text('flutterwave_secret_key')->nullable();
            $table->text('flutterwave_public_key')->nullable();
            $table->text('flutterwave_webhook_secret')->nullable();
            $table->text('stripe_secret_key')->nullable();
            $table->text('stripe_publishable_key')->nullable();
            $table->text('stripe_webhook_secret')->nullable();
            $table->json('allowed_purpose_codes');
            $table->json('allowed_currencies');
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
        Schema::dropIfExists('payment_provider_configurations');
    }
};

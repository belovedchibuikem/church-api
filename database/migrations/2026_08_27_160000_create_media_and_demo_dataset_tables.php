<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_attachments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('attachable_type', 64);
            $table->unsignedBigInteger('attachable_id');
            $table->foreignId('file_asset_id')->constrained('file_assets')->restrictOnDelete();
            $table->string('role', 40);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attachable_type', 'attachable_id', 'role']);
            $table->index(['attachable_type', 'attachable_id', 'sort_order']);
            $table->index(['file_asset_id', 'role']);
        });

        Schema::create('demo_datasets', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_key', 64)->unique();
            $table->timestamp('seeded_at');
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('demo_dataset_records', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_key', 64);
            $table->string('table_name', 64);
            $table->unsignedBigInteger('record_id');
            $table->ulid('public_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['dataset_key', 'table_name', 'record_id']);
            $table->index(['dataset_key', 'table_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_dataset_records');
        Schema::dropIfExists('demo_datasets');
        Schema::dropIfExists('media_attachments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('subject', 191)->nullable();
            $table->timestamps();
        });

        Schema::create('message_conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('message_conversations')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['conversation_id', 'person_id'], 'msg_conv_person_unique');
            $table->index(['person_id', 'conversation_id'], 'msg_person_conv_index');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('conversation_id')->constrained('message_conversations')->cascadeOnDelete();
            $table->foreignId('sender_person_id')->constrained('people')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'messages_conv_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_conversation_participants');
        Schema::dropIfExists('message_conversations');
    }
};

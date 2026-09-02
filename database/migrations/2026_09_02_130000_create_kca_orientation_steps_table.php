<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kca_orientation_steps', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('slug', 64)->unique();
            $table->string('title', 191);
            $table->string('subtitle', 191)->nullable();
            $table->text('body')->nullable();
            $table->string('display_type', 32)->default('content');
            $table->unsignedInteger('sequence')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sequence']);
        });

        $now = now();
        $steps = [
            [
                'public_id' => (string) Str::ulid(),
                'slug' => 'overview',
                'title' => 'Vision & Mission',
                'subtitle' => 'Who we are and where we are going',
                'body' => "THE FAMILY HOUSE OF GOD INTERNATIONAL\n\nVision: Reaching the world with the love of Christ.\n\nMission: Equipping Kingdom leaders who live for Christ and influence the world.",
                'display_type' => 'content',
                'sequence' => 1,
            ],
            [
                'public_id' => (string) Str::ulid(),
                'slug' => 'rules',
                'title' => 'Why KCA',
                'subtitle' => 'Why Kingdom Change Agents exists',
                'body' => "Kingdom Change Agents (KCA) is a youth discipleship training programme designed to help young people know Christ, grow in Christ, serve Christ, and influence their generation for the Kingdom of God.",
                'display_type' => 'content',
                'sequence' => 2,
            ],
            [
                'public_id' => (string) Str::ulid(),
                'slug' => 'path',
                'title' => 'Your Learning Path',
                'subtitle' => 'The journey ahead',
                'body' => null,
                'display_type' => 'modules_list',
                'sequence' => 3,
            ],
            [
                'public_id' => (string) Str::ulid(),
                'slug' => 'mentors',
                'title' => 'Meet Your Mentor',
                'subtitle' => 'Guidance throughout the programme',
                'body' => 'A mentor is assigned after enrollment is activated to walk with you through the programme.',
                'display_type' => 'mentor',
                'sequence' => 4,
            ],
        ];

        foreach ($steps as $step) {
            DB::table('kca_orientation_steps')->insert([
                ...$step,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kca_orientation_steps');
    }
};

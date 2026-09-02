<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->tableExists()) {
            return;
        }

        DB::table('kca_orientation_steps')
            ->where('slug', 'path')
            ->where(function ($query): void {
                $query->whereNull('body')->orWhere('body', '');
            })
            ->update([
                'body' => "Kingdom Change Agents is a structured discipleship journey. Each module builds on the previous one to help you know Christ, grow in Christ, serve Christ, and influence your generation.\n\nThe modules listed below are your published learning path. Open any module to explore the lessons inside.",
                'updated_at' => now(),
            ]);

        DB::table('kca_orientation_steps')
            ->where('slug', 'mentors')
            ->where(function ($query): void {
                $query->whereNull('body')->orWhere('body', 'A mentor is assigned after enrollment is activated to walk with you through the programme.');
            })
            ->update([
                'body' => "A mentor walks with you throughout the KCA programme — encouraging your growth, answering questions, and helping you stay accountable.\n\nYour mentor is assigned after enrollment is activated. Until then, read this step to understand how mentorship works in KCA.",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Content-only seed; no rollback required.
    }

    private function tableExists(): bool
    {
        return DB::getSchemaBuilder()->hasTable('kca_orientation_steps');
    }
};

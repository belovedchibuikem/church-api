<?php

namespace App\Support\Kca;

use App\Models\KcaEnrollment;
use App\Models\KcaGovernanceConfiguration;
use App\Models\KcaModule;
use App\Models\KcaOrientationStep;
use Illuminate\Support\Facades\Schema;
use App\Models\Person;

final class BuildKcaOrientationProgramAction
{
    /**
     * @param  list<string>  $completedStages
     * @return array{
     *     welcome: string,
     *     review_mode: bool,
     *     can_complete: bool,
     *     can_view: bool,
     *     stages: list<array<string, mixed>>
     * }
     */
    public function handle(
        ?Person $person,
        ?KcaEnrollment $enrollment,
        ?string $applicationStatus,
        ?string $orientationCompletedAt,
        array $completedStages,
        bool $canComplete,
    ): array {
        $governance = KcaGovernanceConfiguration::query()->first();
        $reviewMode = $orientationCompletedAt !== null;
        $modules = KcaModule::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->withCount('lessons')
            ->orderBy('sequence')
            ->get(['id', 'public_id', 'title', 'code', 'sequence', 'duration_days']);

        $learningPath = $modules->map(fn (KcaModule $module): array => [
            'id' => $module->public_id,
            'title' => $module->title,
            'code' => $module->code,
            'sequence' => $module->sequence,
            'lessons_count' => $module->lessons_count,
            'duration_days' => $module->duration_days,
        ])->values()->all();

        $mentor = $enrollment === null ? null : app(KcaCurriculumMentorResolver::class)->current($enrollment);
        $mentorName = app(KcaCurriculumMentorResolver::class)->displayName($mentor);

        $steps = Schema::hasTable('kca_orientation_steps')
            ? KcaOrientationStep::query()->active()->ordered()->get()
            : collect();
        if ($steps->isEmpty()) {
            $steps = $this->fallbackSteps();
        }

        $welcome = match (true) {
            $reviewMode && filled($governance?->orientation_review_welcome) => (string) $governance->orientation_review_welcome,
            $reviewMode => 'Revisit the KCA orientation programme — vision, mission, and why we exist.',
            filled($governance?->orientation_welcome) => (string) $governance->orientation_welcome,
            $enrollment !== null => 'Welcome back to KCA. Revisit orientation any time.',
            $applicationStatus === 'interview' => 'Complete each orientation stage, then submit to continue your admission review.',
            default => 'Walk through each stage of the KCA orientation programme.',
        };

        return [
            'welcome' => $welcome,
            'review_mode' => $reviewMode,
            'can_complete' => $canComplete,
            'can_view' => $person !== null,
            'stages' => $steps->map(function (KcaOrientationStep $step) use (
                $completedStages,
                $learningPath,
                $mentor,
                $mentorName,
            ): array {
                $completed = in_array($step->slug, $completedStages, true);

                return match ($step->display_type) {
                    'modules_list' => [
                        'key' => $step->slug,
                        'title' => $step->title,
                        'subtitle' => $step->subtitle,
                        'body' => $step->body,
                        'display_type' => $step->display_type,
                        'modules' => $learningPath,
                        'completed' => $completed,
                    ],
                    'mentor' => [
                        'key' => $step->slug,
                        'title' => $step->title,
                        'subtitle' => $mentorName === null ? ($step->subtitle ?? 'Connect with your mentor') : $mentorName,
                        'body' => $mentor === null ? ($step->body ?? 'A mentor is assigned after enrollment is activated.') : $step->body,
                        'display_type' => $step->display_type,
                        'mentor' => $mentor,
                        'completed' => $completed,
                    ],
                    default => [
                        'key' => $step->slug,
                        'title' => $step->title,
                        'subtitle' => $step->subtitle,
                        'body' => $step->body,
                        'display_type' => 'content',
                        'completed' => $completed,
                    ],
                };
            })->values()->all(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, KcaOrientationStep> */
    private function fallbackSteps(): \Illuminate\Support\Collection
    {
        return collect([
            (new KcaOrientationStep)->forceFill([
                'slug' => 'overview',
                'title' => 'Program Overview',
                'subtitle' => 'About the training',
                'body' => null,
                'display_type' => 'content',
                'sequence' => 1,
                'is_active' => true,
            ]),
            (new KcaOrientationStep)->forceFill([
                'slug' => 'rules',
                'title' => 'Rules & Guidelines',
                'subtitle' => 'What you need to know',
                'body' => null,
                'display_type' => 'content',
                'sequence' => 2,
                'is_active' => true,
            ]),
            (new KcaOrientationStep)->forceFill([
                'slug' => 'path',
                'title' => 'Learning Path',
                'subtitle' => 'Your journey ahead',
                'body' => null,
                'display_type' => 'modules_list',
                'sequence' => 3,
                'is_active' => true,
            ]),
            (new KcaOrientationStep)->forceFill([
                'slug' => 'mentors',
                'title' => 'Meet Your Mentors',
                'subtitle' => 'Connect with your mentors',
                'body' => 'A mentor is assigned after enrollment is activated.',
                'display_type' => 'mentor',
                'sequence' => 4,
                'is_active' => true,
            ]),
        ]);
    }
}

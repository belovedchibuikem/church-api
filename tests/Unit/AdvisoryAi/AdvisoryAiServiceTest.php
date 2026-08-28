<?php

namespace Tests\Unit\AdvisoryAi;

use App\AdvisoryAi\AdvisoryAiService;
use App\AdvisoryAi\AdvisoryRequest;
use App\AdvisoryAi\AdvisoryResponse;
use App\AdvisoryAi\AiContextSanitizer;
use App\AdvisoryAi\Assistant;
use App\AdvisoryAi\Contracts\AdvisoryAiProvider;
use App\AdvisoryAi\DisabledAdvisoryAiProvider;
use App\AdvisoryAi\UseCase;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AdvisoryAiServiceTest extends TestCase
{
    public function test_disabled_provider_fails_closed(): void
    {
        $service = new AdvisoryAiService(new DisabledAdvisoryAiProvider, new AiContextSanitizer);

        $response = $service->advise(new AdvisoryRequest(
            Assistant::Mission,
            UseCase::FollowUpGapDetection,
            'Find gaps.',
        ));

        $this->assertFalse($response->available);
        $this->assertSame('provider_disabled', $response->reasonCode);
        $this->assertTrue($response->requiresHumanDecision);
    }

    public function test_only_use_case_allowlisted_context_reaches_the_provider_boundary(): void
    {
        $provider = new class implements AdvisoryAiProvider
        {
            /** @var array<string, mixed> */
            public array $received = [];

            public function advise(AdvisoryRequest $request): AdvisoryResponse
            {
                $this->received = $request->context;

                return new AdvisoryResponse(true, 'Review the aggregate.', 'advisory_generated');
            }
        };
        $service = new AdvisoryAiService($provider, new AiContextSanitizer);

        $service->advise(new AdvisoryRequest(
            Assistant::Pastoral,
            UseCase::AttendanceAnalysis,
            'Summarize.',
            [
                'period_label' => '<b>August</b>',
                'attendance_total' => 12,
                'pastoral_notes' => 'restricted',
                'nested' => ['api_key' => 'secret', 'safe_count' => 3],
                'lesson_title' => 'Unrelated use-case data',
            ],
        ));

        $this->assertSame([
            'period_label' => 'August',
            'attendance_total' => 12,
        ], $provider->received);
    }

    public function test_instruction_text_is_plain_length_bounded_and_redacts_common_credentials_and_identifiers(): void
    {
        $provider = new class implements AdvisoryAiProvider
        {
            public string $receivedInstruction = '';

            public function advise(AdvisoryRequest $request): AdvisoryResponse
            {
                $this->receivedInstruction = $request->instruction;

                return new AdvisoryResponse(true, 'Review the aggregate.', 'advisory_generated');
            }
        };
        $service = new AdvisoryAiService($provider, new AiContextSanitizer);

        $service->advise(new AdvisoryRequest(
            Assistant::Mission,
            UseCase::FollowUpGapDetection,
            '<b>Find gaps</b> for leader@example.test using api_key=super-secret-value.',
        ));

        $this->assertSame('Find gaps for [redacted] using [redacted]', $provider->receivedInstruction);
    }

    public function test_oversized_instruction_is_rejected_before_the_provider_boundary(): void
    {
        $service = new AdvisoryAiService(new DisabledAdvisoryAiProvider, new AiContextSanitizer);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('may not exceed 1000 characters');

        $service->advise(new AdvisoryRequest(
            Assistant::Mission,
            UseCase::FollowUpGapDetection,
            str_repeat('safe phrase ', 100),
        ));
    }

    public function test_nested_or_associative_context_is_not_forwarded_through_an_allowlisted_key(): void
    {
        $provider = new class implements AdvisoryAiProvider
        {
            /** @var array<string, mixed> */
            public array $received = [];

            public function advise(AdvisoryRequest $request): AdvisoryResponse
            {
                $this->received = $request->context;

                return new AdvisoryResponse(true, 'Review the aggregate.', 'advisory_generated');
            }
        };

        (new AdvisoryAiService($provider, new AiContextSanitizer))->advise(new AdvisoryRequest(
            Assistant::Pastoral,
            UseCase::ReportSummarization,
            'Summarize aggregates.',
            ['metric_values' => ['attendance' => 12, 'api_key' => 'secret']],
        ));

        $this->assertSame([], $provider->received);
    }

    public function test_assistant_use_case_boundaries_are_enforced(): void
    {
        $service = new AdvisoryAiService(new DisabledAdvisoryAiProvider, new AiContextSanitizer);
        $this->expectException(InvalidArgumentException::class);

        $service->advise(new AdvisoryRequest(
            Assistant::Press,
            UseCase::LessonExplanation,
            'Explain this lesson.',
        ));
    }

    public function test_provider_cannot_remove_human_decision_requirement(): void
    {
        $provider = new class implements AdvisoryAiProvider
        {
            public function advise(AdvisoryRequest $request): AdvisoryResponse
            {
                return new AdvisoryResponse(true, 'Automatically approve.', 'unsafe', requiresHumanDecision: false);
            }
        };
        $service = new AdvisoryAiService($provider, new AiContextSanitizer);
        $this->expectException(InvalidArgumentException::class);

        $service->advise(new AdvisoryRequest(
            Assistant::Kca,
            UseCase::StudyGuidance,
            'Guide the student.',
        ));
    }
}

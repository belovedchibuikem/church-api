<?php

namespace App\AdvisoryAi;

use App\AdvisoryAi\Contracts\AdvisoryAiProvider;
use InvalidArgumentException;

final readonly class AdvisoryAiService
{
    public function __construct(
        private AdvisoryAiProvider $provider,
        private AiContextSanitizer $sanitizer,
    ) {}

    public function advise(AdvisoryRequest $request): AdvisoryResponse
    {
        if (! $this->supports($request->assistant, $request->useCase)) {
            throw new InvalidArgumentException('The assistant does not support the requested advisory use case.');
        }

        $safeRequest = new AdvisoryRequest(
            assistant: $request->assistant,
            useCase: $request->useCase,
            instruction: $this->sanitizer->sanitizeInstruction($request->instruction),
            context: $this->sanitizer->sanitize($request->useCase, $request->context),
        );

        $response = $this->provider->advise($safeRequest);

        if (! $response->requiresHumanDecision) {
            throw new InvalidArgumentException('AI responses must preserve authoritative human decision-making.');
        }

        return $response;
    }

    private function supports(Assistant $assistant, UseCase $useCase): bool
    {
        return match ($assistant) {
            Assistant::Pastoral => in_array($useCase, [
                UseCase::ReportSummarization,
                UseCase::AttendanceAnalysis,
                UseCase::FollowUpGapDetection,
            ], true),
            Assistant::Mission => in_array($useCase, [
                UseCase::ReportSummarization,
                UseCase::FollowUpGapDetection,
            ], true),
            Assistant::Kca => in_array($useCase, [
                UseCase::LessonExplanation,
                UseCase::PracticeQuestions,
                UseCase::StudyGuidance,
            ], true),
            Assistant::Press => in_array($useCase, [
                UseCase::PublicationSearch,
                UseCase::CatalogueAssistance,
                UseCase::MetadataSuggestions,
            ], true),
        };
    }
}

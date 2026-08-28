<?php

namespace App\AdvisoryAi;

enum UseCase: string
{
    case ReportSummarization = 'report_summarization';
    case AttendanceAnalysis = 'attendance_analysis';
    case FollowUpGapDetection = 'follow_up_gap_detection';
    case LessonExplanation = 'lesson_explanation';
    case PracticeQuestions = 'practice_questions';
    case StudyGuidance = 'study_guidance';
    case PublicationSearch = 'publication_search';
    case CatalogueAssistance = 'catalogue_assistance';
    case MetadataSuggestions = 'metadata_suggestions';

    /** @return list<string> */
    public function allowedContextKeys(): array
    {
        return match ($this) {
            self::ReportSummarization => [
                'report_type',
                'period_label',
                'metric_labels',
                'metric_values',
            ],
            self::AttendanceAnalysis => [
                'period_label',
                'attendance_total',
                'previous_attendance_total',
                'attendance_rate',
            ],
            self::FollowUpGapDetection => [
                'period_label',
                'due_count',
                'overdue_count',
                'completed_count',
            ],
            self::LessonExplanation => [
                'lesson_title',
                'lesson_excerpt',
                'learning_objectives',
            ],
            self::PracticeQuestions => [
                'lesson_title',
                'topic_codes',
                'learning_objectives',
            ],
            self::StudyGuidance => [
                'progress_percent',
                'completed_module_codes',
                'outstanding_module_codes',
            ],
            self::PublicationSearch => [
                'query',
                'language_code',
                'category_codes',
                'format_codes',
            ],
            self::CatalogueAssistance => [
                'language_code',
                'category_codes',
                'format_codes',
                'availability',
            ],
            self::MetadataSuggestions => [
                'title',
                'subtitle',
                'synopsis',
                'language_code',
                'category_codes',
            ],
        };
    }
}

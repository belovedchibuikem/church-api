<?php

namespace App\Press;

enum PressPublicationType: string
{
    case DocumentPdf = 'document_pdf';
    case Book = 'book';
    case Sermon = 'sermon';
    case Devotional = 'devotional';
    case BibleStudy = 'bible_study';

    public function requiresIsbnToPublish(): bool
    {
        return $this === self::Book;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function normalizeMetadata(array $metadata): array
    {
        $clean = [];
        foreach ($metadata as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function assertCreateRequirements(array $metadata): void
    {
        match ($this) {
            self::Sermon => $this->requireAny(
                $metadata,
                ['speaker', 'preacher', 'speaker_name'],
                'Sermons require a speaker or preacher name.',
            ),
            self::Devotional => $this->requireAny(
                $metadata,
                ['body', 'reflection', 'content'],
                'Devotionals require a reflection or body.',
            ),
            self::BibleStudy => $this->requireAny(
                $metadata,
                ['passage', 'scripture', 'session_passage'],
                'Study manuals require a scripture passage.',
            ),
            self::DocumentPdf, self::Book => null,
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $keys
     */
    private function requireAny(array $metadata, array $keys, string $message): void
    {
        foreach ($keys as $key) {
            $value = $metadata[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return;
            }
        }

        throw new \InvalidArgumentException($message);
    }
}

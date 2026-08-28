<?php

namespace App\Press;

use InvalidArgumentException;

final readonly class PressTranslationData
{
    public string $targetLanguageCode;

    public string $translatedTitle;

    public function __construct(
        string $targetLanguageCode,
        string $translatedTitle,
        public ?string $translatedSubtitle = null,
        public ?string $translatedDescription = null,
        public ?string $translatedContent = null,
    ) {
        $this->targetLanguageCode = LanguageCode::normalize($targetLanguageCode);
        $this->translatedTitle = trim($translatedTitle);

        if ($this->translatedTitle === '') {
            throw new InvalidArgumentException('A translated title is required.');
        }
    }

    /** @return array<string, mixed> */
    public function fingerprintPayload(): array
    {
        return [
            'target_language_code' => $this->targetLanguageCode,
            'translated_title' => $this->translatedTitle,
            'translated_subtitle' => $this->translatedSubtitle,
            'translated_description' => $this->translatedDescription,
            'translated_content' => $this->translatedContent,
        ];
    }
}

<?php

namespace App\Press;

use App\Models\FileAsset;
use InvalidArgumentException;

final readonly class PressPublicationData
{
    public string $title;

    public string $publisherName;

    public string $languageCode;

    public ?string $currencyCode;

    public function __construct(
        string $title,
        string $publisherName,
        string $languageCode,
        public PressPublicationFormat $format,
        public ?string $subtitle = null,
        public ?string $edition = null,
        public ?string $publicationDate = null,
        public ?int $copyrightYear = null,
        public ?int $pageCount = null,
        public ?string $category = null,
        public ?string $description = null,
        public ?FileAsset $coverFileAsset = null,
        public ?FileAsset $contentFileAsset = null,
        public ?int $priceMinor = null,
        ?string $currencyCode = null,
    ) {
        $this->title = self::required($title, 'title');
        $this->publisherName = self::required($publisherName, 'publisher name');
        $this->languageCode = LanguageCode::normalize($languageCode);
        $this->currencyCode = $currencyCode === null ? null : strtoupper(trim($currencyCode));

        if (($priceMinor === null) !== ($this->currencyCode === null)) {
            throw new InvalidArgumentException('Price minor units and currency must be provided together.');
        }

        if ($priceMinor !== null && $priceMinor < 0) {
            throw new InvalidArgumentException('Price minor units cannot be negative.');
        }

        if ($this->currencyCode !== null && preg_match('/\A[A-Z]{3}\z/', $this->currencyCode) !== 1) {
            throw new InvalidArgumentException('Currency must be an uppercase ISO-style three-letter code.');
        }

        if ($pageCount !== null && $pageCount < 1) {
            throw new InvalidArgumentException('Page count must be positive.');
        }

        if ($copyrightYear !== null && ($copyrightYear < 1450 || $copyrightYear > ((int) date('Y') + 1))) {
            throw new InvalidArgumentException('Copyright year is outside the supported range.');
        }

        if ($publicationDate !== null && ! self::isDate($publicationDate)) {
            throw new InvalidArgumentException('Publication date must use YYYY-MM-DD.');
        }
    }

    /** @return array<string, mixed> */
    public function fingerprintPayload(): array
    {
        return [
            'title' => $this->title,
            'publisher_name' => $this->publisherName,
            'language_code' => $this->languageCode,
            'format' => $this->format->value,
            'subtitle' => $this->subtitle,
            'edition' => $this->edition,
            'publication_date' => $this->publicationDate,
            'copyright_year' => $this->copyrightYear,
            'page_count' => $this->pageCount,
            'category' => $this->category,
            'description' => $this->description,
            'cover_file_asset_id' => $this->coverFileAsset?->getKey(),
            'content_file_asset_id' => $this->contentFileAsset?->getKey(),
            'price_minor' => $this->priceMinor,
            'currency_code' => $this->currencyCode,
        ];
    }

    private static function required(string $value, string $field): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException("Publication {$field} is required.");
        }

        return $value;
    }

    private static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}

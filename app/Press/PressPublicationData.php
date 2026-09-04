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

    public ?string $slug;

    public ?string $summary;

    public ?string $contentSourceUrl;

    /** @var array<string, mixed> */
    public array $typeMetadata;

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
        ?string $contentSourceUrl = null,
        public ?int $priceMinor = null,
        ?string $currencyCode = null,
        public PressPublicationType $publicationType = PressPublicationType::Book,
        public PressPublicationVisibility $visibility = PressPublicationVisibility::Public,
        public bool $asDraft = false,
        public bool $featured = false,
        ?string $slug = null,
        ?string $summary = null,
        array $typeMetadata = [],
    ) {
        $this->title = self::required($title, 'title');
        $this->publisherName = self::required($publisherName, 'publisher name');
        $this->languageCode = LanguageCode::normalize($languageCode);
        $this->currencyCode = $currencyCode === null ? null : strtoupper(trim($currencyCode));
        $this->slug = $slug === null || trim($slug) === '' ? null : trim($slug);
        $this->summary = $summary === null || trim($summary) === '' ? null : trim($summary);
        $this->typeMetadata = $this->publicationType->normalizeMetadata($typeMetadata);
        $this->publicationType->assertCreateRequirements($this->typeMetadata);
        $this->contentSourceUrl = self::normalizeUrl($contentSourceUrl);

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
            'publication_type' => $this->publicationType->value,
            'subtitle' => $this->subtitle,
            'edition' => $this->edition,
            'publication_date' => $this->publicationDate,
            'copyright_year' => $this->copyrightYear,
            'page_count' => $this->pageCount,
            'category' => $this->category,
            'description' => $this->description,
            'summary' => $this->summary,
            'slug' => $this->slug,
            'cover_file_asset_id' => $this->coverFileAsset?->getKey(),
            'content_file_asset_id' => $this->contentFileAsset?->getKey(),
            'content_source_url' => $this->contentSourceUrl,
            'price_minor' => $this->priceMinor,
            'currency_code' => $this->currencyCode,
            'visibility' => $this->visibility->value,
            'as_draft' => $this->asDraft,
            'featured' => $this->featured,
            'type_metadata' => $this->typeMetadata,
        ];
    }

    public function initialStatus(): PressPublicationStatus
    {
        return $this->asDraft ? PressPublicationStatus::Draft : PressPublicationStatus::Manuscript;
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

    private static function normalizeUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Content source URL must be a valid http(s) URL.');
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Content source URL must use http or https.');
        }

        return $value;
    }
}

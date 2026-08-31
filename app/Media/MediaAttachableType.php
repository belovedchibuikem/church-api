<?php

namespace App\Media;

use App\Models\Church;
use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\Crusade;
use App\Models\HomeChurch;
use App\Models\MinistryEvent;
use App\Models\Person;
use App\Models\PressPublication;
use App\Models\SafeguardingIncident;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class MediaAttachableType
{
    /** @var array<string, class-string<Model>> */
    public const MAP = [
        'church' => Church::class,
        'home_church' => HomeChurch::class,
        'ministry_event' => MinistryEvent::class,
        'crusade' => Crusade::class,
        'content_page' => ContentPage::class,
        'content_item' => ContentItem::class,
        'person' => Person::class,
        'press_publication' => PressPublication::class,
        'safeguarding_incident' => SafeguardingIncident::class,
    ];

    public static function classFor(string $alias): string
    {
        $class = self::MAP[$alias] ?? null;
        if ($class === null) {
            throw new InvalidArgumentException('Unknown media attachable type.');
        }

        return $class;
    }

    public static function aliasFor(Model $model): string
    {
        $alias = array_search($model::class, self::MAP, true);
        if (! is_string($alias)) {
            throw new InvalidArgumentException('This record cannot receive media attachments.');
        }

        return $alias;
    }

    /** @return array<int, string> */
    public static function aliases(): array
    {
        return array_keys(self::MAP);
    }
}

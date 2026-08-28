<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Platform\ConfigurationClassification;
use App\Platform\ConfigurationValueType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonException;

class PlatformConfigurationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $classification = $this->classification instanceof ConfigurationClassification
            ? $this->classification
            : ConfigurationClassification::from($this->classification);
        $valueType = $this->value_type instanceof ConfigurationValueType
            ? $this->value_type
            : ConfigurationValueType::from($this->value_type);

        return [
            'id' => $this->public_id,
            'key' => $this->key,
            'value_type' => $valueType->value,
            'classification' => $classification->value,
            'environment' => $this->environment,
            'scope' => $this->scope_type === null ? null : [
                'type' => $this->scope_type,
                'id' => $this->scope_key,
            ],
            'value' => $classification === ConfigurationClassification::Internal
                ? $this->decodeInternalValue($valueType, (string) $this->getRawOriginal('stored_value'))
                : null,
            'has_value' => true,
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }

    private function decodeInternalValue(ConfigurationValueType $type, string $storedValue): mixed
    {
        return match ($type) {
            ConfigurationValueType::String => $storedValue,
            ConfigurationValueType::Integer => (int) $storedValue,
            ConfigurationValueType::Boolean => $storedValue === '1',
            ConfigurationValueType::Json => $this->decodeJson($storedValue),
        };
    }

    /** @return array<mixed> */
    private function decodeJson(string $storedValue): array
    {
        try {
            /** @var array<mixed> $value */
            $value = json_decode($storedValue, true, flags: JSON_THROW_ON_ERROR);

            return $value;
        } catch (JsonException) {
            return [];
        }
    }
}

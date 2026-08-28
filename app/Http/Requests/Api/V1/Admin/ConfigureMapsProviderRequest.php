<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Maps\MapsProvider;
use App\Models\MapsProviderConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigureMapsProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'active_provider' => ['required', 'string', Rule::enum(MapsProvider::class)],
            'google_api_key' => ['nullable', 'string', 'max:512'],
            'mapbox_access_token' => ['nullable', 'string', 'max:512'],
            'leaflet_tile_url' => ['nullable', 'string', 'max:2048', 'regex:/\Ahttps:\/\/.+\z/'],
            'default_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'default_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'default_zoom' => ['nullable', 'integer', 'between:1,22'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $provider = $this->input('active_provider');

            if ($provider === MapsProvider::Google->value
                && blank($this->input('google_api_key'))
                && ! $this->existingHasGoogleKey()) {
                $validator->errors()->add('google_api_key', 'A Google Maps API key is required when Google is selected.');
            }

            if ($provider === MapsProvider::Mapbox->value
                && blank($this->input('mapbox_access_token'))
                && ! $this->existingHasMapboxToken()) {
                $validator->errors()->add('mapbox_access_token', 'A Mapbox access token is required when Mapbox is selected.');
            }
        });
    }

    private function existingHasGoogleKey(): bool
    {
        $existing = MapsProviderConfiguration::query()->first();

        return $existing !== null && filled($existing->google_api_key);
    }

    private function existingHasMapboxToken(): bool
    {
        $existing = MapsProviderConfiguration::query()->first();

        return $existing !== null && filled($existing->mapbox_access_token);
    }
}

<?php

namespace App\Support\Organization;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CountryProfile
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $profile = [];

        if (array_key_exists('local_name', $input)) {
            $localName = is_string($input['local_name']) ? Str::squish($input['local_name']) : '';
            $profile['local_name'] = $localName === '' ? null : $localName;
        }

        if (array_key_exists('calling_code', $input)) {
            $calling = is_string($input['calling_code']) ? trim($input['calling_code']) : '';
            $profile['calling_code'] = $calling === '' ? null : $calling;
        }

        if (array_key_exists('currency_code', $input)) {
            $currency = is_string($input['currency_code']) ? strtoupper(trim($input['currency_code'])) : '';
            $profile['currency_code'] = $currency === '' ? null : $currency;
        }

        if (array_key_exists('default_timezone', $input)) {
            $timezone = is_string($input['default_timezone']) ? trim($input['default_timezone']) : '';
            $profile['default_timezone'] = $timezone === '' ? null : $timezone;
        }

        if (array_key_exists('locale', $input)) {
            $locale = is_string($input['locale']) ? trim($input['locale']) : '';
            $profile['locale'] = $locale === '' ? null : $locale;
        }

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public static function persistable(array $profile): array
    {
        if ($profile === [] || ! Schema::hasColumn('countries', 'calling_code')) {
            return [];
        }

        return $profile;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class CountryService
{
    /**
     * Get country ISO code from fiat currency.
     *
     * Example:
     * NGN -> NG
     * KES -> KE
     * GHS -> GH
     */
    public function countryFromFiat(?string $currency): ?string
    {
        $currency = strtoupper(trim((string) $currency));

        if ($currency === '') {
            return null;
        }

        $match = collect($this->countries())->first(function ($country) use ($currency) {
            return strtoupper(
                $country['currency_iso_code'] ?? ''
            ) === $currency;
        });

        return $match['country_iso_code'] ?? null;
    }

    /**
     * Get fiat currency from country ISO code.
     *
     * Example:
     * NG -> NGN
     * KE -> KES
     * GH -> GHS
     */
    public function fiatFromCountryCode(?string $countryCode): ?string
    {
        $countryCode = strtoupper(trim((string) $countryCode));

        if ($countryCode === '') {
            return null;
        }

        $match = collect($this->countries())->first(function ($country) use ($countryCode) {
            return strtoupper(
                $country['country_iso_code'] ?? ''
            ) === $countryCode;
        });

        return $match['currency_iso_code'] ?? null;
    }

    /**
     * Get country details by country ISO code.
     *
     * Example:
     * NG -> [
     *     'country_name' => 'Nigeria',
     *     'country_iso_code' => 'NG',
     *     'currency_iso_code' => 'NGN'
     * ]
     */
    public function getCountry(?string $countryCode): ?array
    {
        $countryCode = strtoupper(trim((string) $countryCode));

        if ($countryCode === '') {
            return null;
        }

        return collect($this->countries())->first(function ($country) use ($countryCode) {
            return strtoupper(
                $country['country_iso_code'] ?? ''
            ) === $countryCode;
        });
    }

    /**
     * Load countries from JSON and cache indefinitely.
     */
    public function countries(): array
    {
        return Cache::rememberForever('countries_json', function () {
            $path = database_path('data/countries.json');

            if (!File::exists($path)) {
                return [];
            }

            $data = json_decode(File::get($path), true);

            return is_array($data) ? $data : [];
        });
    }
}
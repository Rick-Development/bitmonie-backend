<?php

namespace App\Services\YellowCard;

use App\Models\YellowCardCoverage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class YellowCardCoverageService
{
    public function __construct(protected YellowCardClient $client)
    {
    }

    public function refresh(?string $country = null): array
    {
        $response = $this->client->get('/channels', ['country' => $country]);
        $channels = $this->channelsFromResponse($response);
        $syncedAt = now();
        $stored = 0;

        DB::transaction(function () use ($channels, $syncedAt, &$stored) {
            foreach ($channels as $channel) {
                $normalized = $this->normalizeChannel($channel, $syncedAt);

                if (!$normalized['channel_id']) {
                    continue;
                }

                YellowCardCoverage::updateOrCreate(
                    ['channel_id' => $normalized['channel_id']],
                    Arr::except($normalized, ['channel_id']) + ['channel_id' => $normalized['channel_id']]
                );

                $stored++;
            }
        });

        $this->flushCache();

        return [
            'fetched' => count($channels),
            'stored' => $stored,
            'synced_at' => $syncedAt->toDateTimeString(),
        ];
    }

    public function coverage(array $filters = []): array
    {
        $version = Cache::get('yellow-card:coverage:version', 1);
        $cacheKey = 'yellow-card:coverage:' . $version . ':' . md5(json_encode($filters));
        $ttl = (int) config('services.yellow_card.coverage_cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($filters) {
            $query = YellowCardCoverage::query()
                ->when($filters['country'] ?? null, fn ($q, $country) => $q->where('country_code', strtoupper($country)))
                ->when($filters['currency'] ?? null, fn ($q, $currency) => $q->where('currency_code', strtoupper($currency)))
                ->when($filters['channel_type'] ?? null, fn ($q, $channelType) => $q->where('channel_type', strtolower($channelType)))
                ->when($filters['ramp_type'] ?? null, fn ($q, $rampType) => $q->where('ramp_type', strtolower($rampType)))
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

            $channels = $query->orderBy('country_name')
                ->orderBy('currency_code')
                ->orderBy('ramp_type')
                ->orderBy('channel_type')
                ->get()
                ->map(fn (YellowCardCoverage $coverage) => $this->presentChannel($coverage))
                ->values();

            return [
                'countries' => $this->groupCountries($channels),
                'channels' => $channels,
                'last_synced_at' => YellowCardCoverage::max('last_synced_at'),
            ];
        });
    }

    public function flushCache(): void
    {
        Cache::forever('yellow-card:coverage:version', (int) Cache::get('yellow-card:coverage:version', 1) + 1);
    }

    public function channelsFromResponse(array $response): array
    {
        $candidates = [
            $response,
            data_get($response, 'data'),
            data_get($response, 'channels'),
            data_get($response, 'data.channels'),
            data_get($response, 'message.channels'),
            data_get($response, 'result'),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && array_is_list($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    public function normalizeChannel(array $channel, $syncedAt = null): array
    {
        $channelId = $this->first($channel, ['id', 'channelId', 'channel_id', 'uuid']);
        $countryCode = strtoupper((string) $this->first($channel, ['country', 'countryCode', 'country_code', 'country.isoCode', 'country.code']));
        $currencyCode = strtoupper((string) $this->first($channel, ['currency', 'currencyCode', 'currency_code', 'currency.code']));
        $channelType = strtolower((string) $this->first($channel, ['channelType', 'channel_type', 'type']));
        $rampType = strtolower((string) $this->first($channel, ['rampType', 'ramp_type', 'ramp']));

        if (!$channelId) {
            $channelId = Str::lower(implode(':', array_filter([
                $countryCode,
                $currencyCode,
                $rampType,
                $channelType,
                (string) $this->first($channel, ['paymentMethod', 'payment_method', 'name']),
            ])));
        }

        return [
            'channel_id' => $channelId,
            'country_code' => $countryCode ?: null,
            'country_name' => $this->first($channel, ['countryName', 'country_name', 'country.name']) ?: $countryCode ?: null,
            'currency_code' => $currencyCode ?: null,
            'channel_type' => $channelType ?: null,
            'ramp_type' => $rampType ?: null,
            'payment_method' => $this->first($channel, ['paymentMethod', 'payment_method', 'method', 'name']) ?: $this->paymentMethodLabel($channelType),
            'payment_type' => $this->first($channel, ['paymentType', 'payment_type', 'processingType']),
            'settlement_time' => $this->first($channel, ['settlementTime', 'settlement_time', 'settlement']),
            'status' => $this->first($channel, ['status', 'state']) ?: null,
            'min_amount' => $this->decimalOrNull($this->first($channel, ['min', 'minimum', 'minAmount', 'min_amount', 'limits.min'])),
            'max_amount' => $this->decimalOrNull($this->first($channel, ['max', 'maximum', 'maxAmount', 'max_amount', 'limits.max'])),
            'raw' => $channel,
            'last_synced_at' => $syncedAt ?: now(),
        ];
    }

    protected function presentChannel(YellowCardCoverage $coverage): array
    {
        return [
            'channel_id' => $coverage->channel_id,
            'country_code' => $coverage->country_code,
            'country_name' => $coverage->country_name,
            'currency_code' => $coverage->currency_code,
            'channel_type' => $coverage->channel_type,
            'ramp_type' => $coverage->ramp_type,
            'payment_method' => $coverage->payment_method,
            'payment_type' => $coverage->payment_type,
            'settlement_time' => $coverage->settlement_time,
            'status' => $coverage->status,
            'min_amount' => $coverage->min_amount,
            'max_amount' => $coverage->max_amount,
        ];
    }

    protected function groupCountries($channels): array
    {
        return $channels
            ->groupBy('country_code')
            ->map(function ($items, $countryCode) {
                return [
                    'country_code' => $countryCode,
                    'country_name' => $items->first()['country_name'] ?? $countryCode,
                    'currencies' => $items->pluck('currency_code')->filter()->unique()->values(),
                    'payment_methods' => $items->pluck('channel_type')->filter()->unique()->values(),
                    'ramps' => $items->pluck('ramp_type')->filter()->unique()->values(),
                    'channels_count' => $items->count(),
                ];
            })
            ->values()
            ->all();
    }

    protected function first(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function paymentMethodLabel(?string $channelType): ?string
    {
        return match ($channelType) {
            'bank' => 'Bank Transfer',
            'momo' => 'Mobile Money',
            default => $channelType ? Str::headline($channelType) : null,
        };
    }

    protected function decimalOrNull(mixed $value): ?string
    {
        return is_numeric($value) ? (string) $value : null;
    }
}

<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * GraphDataController
 *
 * Proxy endpoints for real-time and historical cryptocurrency price data.
 * Data is sourced from CoinGecko's free public API (no key required).
 * Responses are cached to optimise performance for mobile consumption.
 *
 * Endpoints:
 *   GET /api/graph/prices?symbol=BTC&interval=1d
 *   GET /api/graph/history?symbol=BTC&days=7
 */
class GraphDataController extends Controller
{
    /**
     * CoinGecko coin ID map — extend as more assets are supported.
     */
    protected array $coinMap = [
        'BTC'  => 'bitcoin',
        'ETH'  => 'ethereum',
        'USDT' => 'tether',
        'USDC' => 'usd-coin',
        'BNB'  => 'binancecoin',
        'SOL'  => 'solana',
        'XRP'  => 'ripple',
        'ADA'  => 'cardano',
        'TRX'  => 'tron',
        'MATIC'=> 'matic-network',
        'DOGE' => 'dogecoin',
        'LTC'  => 'litecoin',
        'DOT'  => 'polkadot',
        'AVAX' => 'avalanche-2',
        'LINK' => 'chainlink',
    ];

    /** Interval → CoinGecko 'days' mapping */
    protected array $intervalMap = [
        '1h'  => 1,
        '4h'  => 1,
        '1d'  => 1,
        '7d'  => 7,
        '14d' => 14,
        '30d' => 30,
        '90d' => 90,
        '1y'  => 365,
    ];

    // =========================================================================
    // GET /api/graph/prices?symbol=BTC&interval=1d
    // =========================================================================

    /**
     * Returns real-time price + 24h OHLCV data for a given crypto symbol.
     * Cached for 60 seconds.
     */
    public function prices(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symbol'   => 'required|string|max:10',
            'interval' => 'nullable|in:1h,4h,1d,7d,14d,30d,90d,1y',
            'vs'       => 'nullable|in:usd,ngn,eur,gbp',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        $symbol   = strtoupper(trim($request->symbol));
        $interval = $request->get('interval', '1d');
        $vs       = strtolower($request->get('vs', 'usd'));
        $coinId   = $this->coinMap[$symbol] ?? null;

        if (!$coinId) {
            return Response::errorResponse(
                "Symbol '{$symbol}' is not supported. Supported: " . implode(', ', array_keys($this->coinMap)),
                null,
                422
            );
        }

        $cacheKey = "graph_prices_{$coinId}_{$vs}_{$interval}";

        $data = Cache::remember($cacheKey, 60, function () use ($coinId, $vs, $interval) {
            return $this->fetchPriceData($coinId, $vs, $interval);
        });

        if ($data === null) {
            return Response::errorResponse('Failed to fetch price data. Please try again shortly.', null, 503);
        }

        return Response::successResponse('Graph data fetched successfully', $data);
    }

    // =========================================================================
    // GET /api/graph/history?symbol=BTC&days=7
    // =========================================================================

    /**
     * Returns historical price data (OHLCV candles) for a given symbol.
     * Cached for 5 minutes.
     */
    public function history(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string|max:10',
            'days'   => 'nullable|integer|min:1|max:365',
            'vs'     => 'nullable|in:usd,ngn,eur,gbp',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        $symbol = strtoupper(trim($request->symbol));
        $days   = (int) $request->get('days', 7);
        $vs     = strtolower($request->get('vs', 'usd'));
        $coinId = $this->coinMap[$symbol] ?? null;

        if (!$coinId) {
            return Response::errorResponse(
                "Symbol '{$symbol}' is not supported. Supported: " . implode(', ', array_keys($this->coinMap)),
                null,
                422
            );
        }

        $cacheKey = "graph_history_{$coinId}_{$vs}_{$days}d";

        $data = Cache::remember($cacheKey, 300, function () use ($coinId, $vs, $days) {
            return $this->fetchHistoryData($coinId, $vs, $days);
        });

        if ($data === null) {
            return Response::errorResponse('Failed to fetch historical data. Please try again shortly.', null, 503);
        }

        return Response::successResponse('Graph data fetched successfully', $data);
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Fetch current price + market data from CoinGecko.
     * Returns the standardised response array or null on failure.
     */
    protected function fetchPriceData(string $coinId, string $vs, string $interval): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get('https://api.coingecko.com/api/v3/coins/' . $coinId, [
                    'localization'   => false,
                    'tickers'        => false,
                    'market_data'    => true,
                    'community_data' => false,
                    'developer_data' => false,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $json       = $response->json();
            $market     = $json['market_data'] ?? [];
            $currentPrice = $market['current_price'][$vs] ?? null;

            if ($currentPrice === null) {
                return null;
            }

            return [
                'symbol'              => strtoupper($json['symbol'] ?? $coinId),
                'name'                => $json['name'] ?? $coinId,
                'interval'            => $interval,
                'vs_currency'         => strtoupper($vs),
                'current_price'       => $currentPrice,
                'price_change_24h'    => $market['price_change_24h'] ?? null,
                'price_change_pct_24h'=> round($market['price_change_percentage_24h'] ?? 0, 2),
                'high_24h'            => $market['high_24h'][$vs] ?? null,
                'low_24h'             => $market['low_24h'][$vs] ?? null,
                'market_cap'          => $market['market_cap'][$vs] ?? null,
                'volume_24h'          => $market['total_volume'][$vs] ?? null,
                'last_updated'        => $json['last_updated'] ?? null,
                // Standardised candle format matching the required schema
                'data'                => [
                    [
                        'timestamp' => now()->timestamp,
                        'price'     => $currentPrice,
                    ],
                ],
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Fetch historical OHLCV price data from CoinGecko.
     * Returns the standardised response array or null on failure.
     */
    protected function fetchHistoryData(string $coinId, string $vs, int $days): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['Accept' => 'application/json'])
                ->get('https://api.coingecko.com/api/v3/coins/' . $coinId . '/market_chart', [
                    'vs_currency' => $vs,
                    'days'        => $days,
                    'interval'    => $days <= 1 ? 'hourly' : 'daily',
                ]);

            if (!$response->successful()) {
                return null;
            }

            $json = $response->json();

            // Map CoinGecko [timestamp_ms, price] format to our standard schema
            $prices = collect($json['prices'] ?? [])->map(function ($point) {
                return [
                    'timestamp' => (int) ($point[0] / 1000), // Convert ms → seconds
                    'price'     => (float) $point[1],
                ];
            })->values()->toArray();

            if (empty($prices)) {
                return null;
            }

            $priceValues = array_column($prices, 'price');

            return [
                'symbol'      => strtoupper($coinId),
                'vs_currency' => strtoupper($vs),
                'days'        => $days,
                'data_points' => count($prices),
                'price_high'  => max($priceValues),
                'price_low'   => min($priceValues),
                'data'        => $prices,
            ];
        } catch (Exception $e) {
            return null;
        }
    }
}

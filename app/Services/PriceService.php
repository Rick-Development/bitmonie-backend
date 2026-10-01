<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PriceService
{
    protected $baseUrl = 'https://api.coingecko.com/api/v3';

    /**
     * Get prices for multiple assets at once.
     * 
     * @param array $symbols Array of symbols like ['BTC', 'USDT', 'BNB']
     * @return array Mapping of symbols to prices e.g. ['BTC' => 64000.5, 'USDT' => 1.0]
     */
    public function getPrices(array $symbols): array
    {
        $mapping = $this->getCoingeckoIdMapping();
        $ids = [];
        $reverseMapping = [];

        foreach ($symbols as $symbol) {
            $upper = strtoupper($symbol);
            if (isset($mapping[$upper])) {
                $cgId = $mapping[$upper];
                $ids[] = $cgId;
                $reverseMapping[$cgId] = $upper;
            }
        }

        if (empty($ids)) {
            return [];
        }

        try {
            $response = Http::get($this->baseUrl . '/simple/price', [
                'ids' => implode(',', $ids),
                'vs_currencies' => 'usd',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $prices = [];
                foreach ($data as $cgId => $val) {
                    if (isset($reverseMapping[$cgId])) {
                        $prices[$reverseMapping[$cgId]] = (float) ($val['usd'] ?? 1.0);
                    }
                }
                
                // Add default stables if not returned correctly but requested
                if (in_array('USDT', $symbols) && !isset($prices['USDT'])) $prices['USDT'] = 1.0;
                if (in_array('USDC', $symbols) && !isset($prices['USDC'])) $prices['USDC'] = 1.0;

                return $prices;
            }

            Log::error("CoinGecko API failure: " . $response->body());
            return [];

        } catch (Exception $e) {
            Log::error("CoinGecko Exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get price for a single asset.
     */
    public function getPrice(string $symbol): float
    {
        $prices = $this->getPrices([$symbol]);
        return $prices[strtoupper($symbol)] ?? 0.0;
    }

    /**
     * Mapping of our symbols to Coingecko IDs.
     */
    protected function getCoingeckoIdMapping(): array
    {
        return [
            'BTC'   => 'bitcoin',
            'ETH'   => 'ethereum',
            'USDT'  => 'tether',
            'USDC'  => 'usd-coin',
            'SOL'   => 'solana',
            'BNB'   => 'binancecoin',
            'LTC'   => 'litecoin',
            'DOGE'  => 'dogecoin',
            'XRP'   => 'ripple',
            'MATIC' => 'polygon-ecosystem-token',
            'NGN'   => 'nigerian-naira',
        ];
    }
}

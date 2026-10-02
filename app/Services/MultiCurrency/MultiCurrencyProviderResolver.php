<?php

namespace App\Services\MultiCurrency;

use App\Services\MultiCurrency\Contracts\CurrencyProviderInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use RuntimeException;

class MultiCurrencyProviderResolver
{
    /**
     * Map of currency => provider binding key.
     *
     * All currently-supported currencies resolve to Fincra.
     * When a second provider is added, only this map (and
     * config) needs to change — not the calling services.
     */
    protected array $currencyProviderMap = [
        'NGN' => 'fincra',
        'GHS' => 'fincra',
        'KES' => 'fincra',
        'TZS' => 'fincra',
    ];

    /**
     * Provider binding key => concrete class.
     */
    protected array $providers = [
        'fincra' => \App\Services\MultiCurrency\Providers\FincraService::class,
    ];

    /**
     * Provider key used when no currency-specific provider
     * applies (e.g. quotes with no source currency).
     */
    protected string $defaultProviderKey = 'fincra';

    /**
     * Resolved provider instances, cached per request.
     *
     * @var array<string, CurrencyProviderInterface>
     */
    protected array $resolved = [];

    public function __construct(
        protected Container $container
    ) {
    }

    /**
     * Resolve the provider for a given currency.
     */
    public function resolve(string $currency): CurrencyProviderInterface
    {
        $currency = strtoupper($currency);

        $providerKey = $this->currencyProviderMap[$currency]
            ?? null;

        if (!$providerKey) {
            throw new InvalidArgumentException(
                "No multicurrency provider is configured for {$currency}."
            );
        }

        return $this->resolveProvider($providerKey);
    }

    /**
     * Resolve the default provider.
     *
     * Used where there is no currency to key off of
     * (e.g. a conversion quote with no source currency).
     */
    public function resolveDefault(): CurrencyProviderInterface
    {
        return $this->resolveProvider(
            $this->defaultProviderKey
        );
    }

    /**
     * Resolve and cache a provider instance by its binding key.
     */
    protected function resolveProvider(
        string $providerKey
    ): CurrencyProviderInterface {
        if (isset($this->resolved[$providerKey])) {
            return $this->resolved[$providerKey];
        }

        $class = $this->providers[$providerKey]
            ?? null;

        if (!$class) {
            throw new RuntimeException(
                "No provider class registered for '{$providerKey}'."
            );
        }

        $instance = $this->container->make($class);

        if (!$instance instanceof CurrencyProviderInterface) {
            throw new RuntimeException(
                "Provider '{$providerKey}' does not implement CurrencyProviderInterface."
            );
        }

        return $this->resolved[$providerKey] = $instance;
    }
}
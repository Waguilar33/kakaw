<?php

namespace Kakaw\Certifiers\Digifact;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;

class TokenStore
{
    private const SAFETY_MARGIN_MINUTES = 5;

    private const DEFAULT_TTL_HOURS = 1;

    public function __construct(private readonly Repository $cache) {}

    public function get(DigifactConfig $config): ?string
    {
        $token = $this->cache->get($config->cacheKey());

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function put(DigifactConfig $config, string $token, ?CarbonImmutable $expiresAt): void
    {
        $validUntil = $expiresAt?->subMinutes(self::SAFETY_MARGIN_MINUTES)
            ?? CarbonImmutable::now()->addHours(self::DEFAULT_TTL_HOURS);

        if ($validUntil->isPast()) {
            return;
        }

        $this->cache->put($config->cacheKey(), $token, $validUntil);
    }

    public function forget(DigifactConfig $config): void
    {
        $this->cache->forget($config->cacheKey());
    }
}

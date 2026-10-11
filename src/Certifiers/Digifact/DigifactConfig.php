<?php

namespace Kakaw\Certifiers\Digifact;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kakaw\Exceptions\InvalidConfigurationException;

final readonly class DigifactConfig
{
    private const REQUIRED_KEYS = ['tax_id', 'username', 'password'];

    public function __construct(
        public string $taxId,
        public string $username,
        public string $password,
        public Environment $environment,
        public string $baseUrl,
        public int $timeout,
        public int $connectTimeout,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $missing = Collection::make(self::REQUIRED_KEYS)
            ->filter(fn (string $key): bool => self::text($config, $key) === '')
            ->values()
            ->all();

        if ($missing !== []) {
            throw InvalidConfigurationException::missing('digifact', $missing);
        }

        $environmentName = self::text($config, 'environment');
        $environment = $environmentName === '' ? Environment::Test : Environment::tryFrom($environmentName);

        if ($environment === null) {
            throw InvalidConfigurationException::invalid('digifact', 'environment', $environmentName);
        }
        $baseUrl = self::text($config, 'base_url');

        return new self(
            taxId: self::text($config, 'tax_id'),
            username: self::text($config, 'username'),
            password: self::text($config, 'password'),
            environment: $environment,
            baseUrl: $baseUrl !== '' ? $baseUrl : $environment->baseUrl(),
            timeout: (int) Arr::get($config, 'timeout', 300),
            connectTimeout: (int) Arr::get($config, 'connect_timeout', 5),
        );
    }

    public function paddedTaxId(): string
    {
        return Str::padLeft(Str::remove('-', $this->taxId), 12, '0');
    }

    public function loginUsername(): string
    {
        return "GT.{$this->paddedTaxId()}.{$this->username}";
    }

    public function cacheKey(): string
    {
        $hash = sha1("{$this->paddedTaxId()}|{$this->username}");

        return "kakaw:digifact:{$this->environment->value}:{$hash}";
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function text(array $config, string $key): string
    {
        $value = Arr::get($config, $key);

        return is_scalar($value) ? trim((string) $value) : '';
    }
}

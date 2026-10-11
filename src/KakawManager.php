<?php

namespace Kakaw;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use Kakaw\Certifiers\Digifact\DigifactCertifier;
use Kakaw\Certifiers\Digifact\DigifactClient;
use Kakaw\Certifiers\Digifact\DigifactConfig;
use Kakaw\Certifiers\Digifact\TokenStore;
use Kakaw\Contracts\Certifier;
use Kakaw\Contracts\IssuesTotalCreditNotes;
use Kakaw\Data\Issuer;
use Kakaw\Data\Results\CertifiedDocument;
use Kakaw\Data\TotalCreditNote;
use Kakaw\Exceptions\InvalidConfigurationException;
use Kakaw\Exceptions\UnsupportedOperationException;

class KakawManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('kakaw.default');
    }

    public function certifier(?string $name = null): Certifier
    {
        /** @var Certifier */
        return $this->driver($name);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function build(array $config): Certifier
    {
        return $this->resolve($config, (string) ($config['driver'] ?? ''));
    }

    public function creditNote(TotalCreditNote $creditNote): CertifiedDocument
    {
        $certifier = $this->certifier();

        if (! $certifier instanceof IssuesTotalCreditNotes) {
            throw UnsupportedOperationException::for($this->getDefaultDriver(), 'creditNote');
        }

        return $certifier->creditNote($creditNote);
    }

    public function issuer(): Issuer
    {
        return Issuer::fromArray((array) $this->config->get('kakaw.issuer', []));
    }

    protected function createDriver($driver): Certifier
    {
        $config = $this->config->get("kakaw.certifiers.{$driver}");

        if (! is_array($config)) {
            throw InvalidConfigurationException::unknownCertifier((string) $driver);
        }

        return $this->resolve($config, (string) $driver);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createDigifactCertifier(array $config): Certifier
    {
        $digifactConfig = DigifactConfig::fromArray($config);

        $cacheStore = $this->container->make('cache')->store($this->config->get('kakaw.cache.store'));

        $http = $this->container->make(Factory::class);

        return new DigifactCertifier(
            new DigifactClient($http, new TokenStore($cacheStore), $digifactConfig),
            $digifactConfig,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolve(array $config, string $name): Certifier
    {
        $driver = (string) ($config['driver'] ?? '');

        if (blank($driver)) {
            throw InvalidConfigurationException::unknownCertifier($name);
        }

        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($this->container, $config);
        }

        $method = 'create'.Str::studly($driver).'Certifier';

        if (! method_exists($this, $method)) {
            throw InvalidConfigurationException::unknownCertifier($name);
        }

        return $this->{$method}($config);
    }
}

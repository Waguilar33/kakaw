<?php

use Kakaw\Certifiers\Digifact\DigifactConfig;
use Kakaw\Certifiers\Digifact\Environment;
use Kakaw\Exceptions\InvalidConfigurationException;

function digifactConfigArray(array $overrides = []): array
{
    return array_merge([
        'tax_id' => '1234567-8',
        'username' => 'APIUSER',
        'password' => 'secret',
    ], $overrides);
}

it('names the missing password', function () {
    $config = digifactConfigArray();
    unset($config['password']);

    expect(fn () => DigifactConfig::fromArray($config))
        ->toThrow(InvalidConfigurationException::class, 'password');
});

it('names every missing key', function () {
    expect(fn () => DigifactConfig::fromArray(['tax_id' => ' ', 'username' => 'u']))
        ->toThrow(InvalidConfigurationException::class, 'tax_id, password');
});

it('pads the tax id to twelve digits without hyphens', function () {
    expect(DigifactConfig::fromArray(digifactConfigArray())->paddedTaxId())->toBe('000012345678');
});

it('builds the login username', function () {
    expect(DigifactConfig::fromArray(digifactConfigArray())->loginUsername())->toBe('GT.000012345678.APIUSER');
});

it('defaults to the test environment and its timeouts', function () {
    $config = DigifactConfig::fromArray(digifactConfigArray());

    expect($config->environment)->toBe(Environment::Test)
        ->and($config->baseUrl)->toBe('https://testnucgt.digifact.com/api/')
        ->and($config->timeout)->toBe(60)
        ->and($config->connectTimeout)->toBe(5);
});

it('resolves the production base url', function () {
    $config = DigifactConfig::fromArray(digifactConfigArray(['environment' => 'production']));

    expect($config->baseUrl)->toBe('https://nucgt.digifact.com/gt.com.apinuc/api/');
});

it('lets a filled base url override the environment', function () {
    $config = DigifactConfig::fromArray(digifactConfigArray(['environment' => 'production', 'base_url' => 'https://digifact.test/api/']));

    expect($config->baseUrl)->toBe('https://digifact.test/api/');
});

it('ignores a blank base url', function () {
    $config = DigifactConfig::fromArray(digifactConfigArray(['base_url' => '']));

    expect($config->baseUrl)->toBe('https://testnucgt.digifact.com/api/');
});

it('derives different cache keys for different usernames', function () {
    $first = DigifactConfig::fromArray(digifactConfigArray());
    $second = DigifactConfig::fromArray(digifactConfigArray(['username' => 'OTHER']));

    expect($first->cacheKey())->not->toBe($second->cacheKey())
        ->and($first->cacheKey())->toStartWith('kakaw:digifact:test:');
});

it('rejects an unknown environment instead of falling back to test', function () {
    expect(fn () => DigifactConfig::fromArray(digifactConfigArray(['environment' => 'prod'])))
        ->toThrow(InvalidConfigurationException::class, 'environment');
});

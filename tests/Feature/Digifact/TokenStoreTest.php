<?php

use Carbon\CarbonImmutable;
use Kakaw\Certifiers\Digifact\DigifactConfig;
use Kakaw\Certifiers\Digifact\TokenStore;

function tokenStoreConfig(): DigifactConfig
{
    return DigifactConfig::fromArray(['tax_id' => '1234567-8', 'username' => 'APIUSER', 'password' => 'secret']);
}

function tokenStore(): TokenStore
{
    return new TokenStore(app('cache')->store());
}

it('returns a token stored with a future expiry', function () {
    tokenStore()->put(tokenStoreConfig(), 't', CarbonImmutable::now()->addHour());

    expect(tokenStore()->get(tokenStoreConfig()))->toBe('t');
});

it('stores nothing for a past expiry', function () {
    tokenStore()->put(tokenStoreConfig(), 't', CarbonImmutable::now()->subMinute());

    expect(tokenStore()->get(tokenStoreConfig()))->toBeNull();
});

it('stores nothing when the expiry is inside the safety margin', function () {
    tokenStore()->put(tokenStoreConfig(), 't', CarbonImmutable::now()->addMinutes(3));

    expect(tokenStore()->get(tokenStoreConfig()))->toBeNull();
});

it('keeps a token for an hour when there is no expiry', function () {
    tokenStore()->put(tokenStoreConfig(), 't', null);

    expect(tokenStore()->get(tokenStoreConfig()))->toBe('t');

    $this->travel(61)->minutes();

    expect(tokenStore()->get(tokenStoreConfig()))->toBeNull();
});

it('forgets a token', function () {
    tokenStore()->put(tokenStoreConfig(), 't', null);
    tokenStore()->forget(tokenStoreConfig());

    expect(tokenStore()->get(tokenStoreConfig()))->toBeNull();
});

it('returns null when nothing is stored', function () {
    expect(tokenStore()->get(tokenStoreConfig()))->toBeNull();
});

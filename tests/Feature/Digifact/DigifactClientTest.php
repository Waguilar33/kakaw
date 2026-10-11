<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kakaw\Certifiers\Digifact\DigifactClient;
use Kakaw\Certifiers\Digifact\DigifactConfig;
use Kakaw\Certifiers\Digifact\TokenStore;
use Kakaw\Exceptions\AuthenticationException;
use Kakaw\Exceptions\CertifierException;

function digifactTestConfig(): DigifactConfig
{
    return DigifactConfig::fromArray([
        'tax_id' => '1234567-8',
        'username' => 'APIUSER',
        'password' => 'secret',
        'base_url' => 'https://digifact.test/api/',
    ]);
}

function digifactTestStore(): TokenStore
{
    return new TokenStore(app('cache')->store());
}

function digifactClient(): DigifactClient
{
    return new DigifactClient(app(Factory::class), digifactTestStore(), digifactTestConfig());
}

it('sends the username and password to the login endpoint', function () {
    Http::fake(['*/login/get_token' => Http::response(['Token' => 't'])]);

    digifactClient()->login();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://digifact.test/api/login/get_token'
        && $request['Username'] === 'GT.000012345678.APIUSER'
        && $request['Password'] === 'secret');
});

it('returns and caches the token on login', function () {
    Http::fake(['*/login/get_token' => Http::response(['Token' => 't', 'expira_en' => '2/4/2999 3:53:21 PM'])]);

    expect(digifactClient()->login())->toBe('t')
        ->and(digifactTestStore()->get(digifactTestConfig()))->toBe('t');
});

it('reads a lower-case token key', function () {
    Http::fake(['*/login/get_token' => Http::response(['token' => 't'])]);

    expect(digifactClient()->login())->toBe('t');
});

it('treats an unparseable expiration as absent', function () {
    Http::fake(['*/login/get_token' => Http::response(['Token' => 't', 'expira_en' => 'soon'])]);

    digifactClient()->login();

    expect(digifactTestStore()->get(digifactTestConfig()))->toBe('t');
});

it('parses the expiration in Guatemala time', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-02-04 21:00:00', 'UTC'));
    Http::fake(['*/login/get_token' => Http::response(['Token' => 't', 'expira_en' => '2/4/2027 3:30:00 PM'])]);

    digifactClient()->login();

    expect(digifactTestStore()->get(digifactTestConfig()))->toBe('t');

    CarbonImmutable::setTestNow();
});

it('surfaces the message digifact returns when the login is rejected', function () {
    Http::fake(['*/login/get_token' => Http::response(['response' => 'Credenciales incorrectas'])]);

    expect(fn () => digifactClient()->login())
        ->toThrow(AuthenticationException::class, 'Credenciales incorrectas');
});

it('falls back to the raw body when the login response is not json', function () {
    Http::fake(['*/login/get_token' => Http::response('upstream exploded', 500)]);

    expect(fn () => digifactClient()->login())
        ->toThrow(AuthenticationException::class, 'upstream exploded');
});

it('logs in first and sends the raw token when none is cached', function () {
    Http::fake([
        '*/login/get_token' => Http::response(['Token' => 't']),
        '*/Shared*' => Http::response(['ok' => true]),
    ]);

    $result = digifactClient()->get('Shared', ['TaxID' => '1']);

    expect($result)->toBe(['ok' => true]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/Shared')
        && $request->header('Authorization') === ['t']
        && str_contains($request->url(), 'TaxID=1'));
});

it('reuses a cached token without logging in', function () {
    digifactTestStore()->put(digifactTestConfig(), 'cached', null);
    Http::fake(['*/Shared*' => Http::response(['ok' => true])]);

    digifactClient()->get('Shared');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'login/get_token'));
    Http::assertSent(fn (Request $request) => $request->header('Authorization') === ['cached']);
});

it('posts the body and query with the token', function () {
    digifactTestStore()->put(digifactTestConfig(), 't', null);
    Http::fake(['*/v2/transform*' => Http::response(['done' => true])]);

    $result = digifactClient()->post('v2/transform', ['a' => 1], ['FORMAT' => 'XML']);

    expect($result)->toBe(['done' => true]);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), 'FORMAT=XML')
        && $request['a'] === 1);
});

it('logs in again after a 401 and returns the second response', function () {
    digifactTestStore()->put(digifactTestConfig(), 'stale', null);
    Http::fake([
        '*/login/get_token' => Http::response(['Token' => 'fresh']),
        '*/Shared*' => Http::sequence()
            ->push('', 401)
            ->push(['ok' => true]),
    ]);

    expect(digifactClient()->get('Shared'))->toBe(['ok' => true]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'login/get_token'));
    Http::assertSent(fn (Request $request) => $request->header('Authorization') === ['fresh']);
});

it('throws when the retry is also unauthorized', function () {
    digifactTestStore()->put(digifactTestConfig(), 'stale', null);
    Http::fake([
        '*/login/get_token' => Http::response(['Token' => 'fresh']),
        '*/Shared*' => Http::response('', 401),
    ]);

    expect(fn () => digifactClient()->get('Shared'))->toThrow(AuthenticationException::class);
});

it('throws when a successful response is not json', function () {
    digifactTestStore()->put(digifactTestConfig(), 't', null);
    Http::fake(['*/Shared*' => Http::response('<html>', 200)]);

    expect(fn () => digifactClient()->get('Shared'))->toThrow(CertifierException::class, '<html>');
});

it('wraps a connection failure', function () {
    digifactTestStore()->put(digifactTestConfig(), 't', null);
    Http::fake(['*/Shared*' => fn () => throw new ConnectionException('timed out')]);

    try {
        digifactClient()->get('Shared');
    } catch (CertifierException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(ConnectionException::class);

        return;
    }

    $this->fail('Expected a CertifierException.');
});

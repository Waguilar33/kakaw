<?php

namespace Kakaw\Certifiers\Digifact;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Kakaw\Exceptions\AuthenticationException;
use Kakaw\Exceptions\CertifierException;
use Throwable;

class DigifactClient
{
    private const CERTIFIER = 'digifact';

    private const LOGIN_PATH = 'login/get_token';

    private const EXPIRATION_FORMAT = 'n/j/Y g:i:s A';

    private const EXPIRATION_TIMEZONE = 'America/Guatemala';

    public function __construct(
        private readonly Factory $http,
        private readonly TokenStore $tokens,
        private readonly DigifactConfig $config,
    ) {}

    public function login(): string
    {
        $response = $this->dispatch(fn (): Response => $this->request()->post(self::LOGIN_PATH, [
            'Username' => $this->config->loginUsername(),
            'Password' => $this->config->password,
        ]));

        $data = $response->json();
        $data = is_array($data) ? array_change_key_case($data) : [];

        $token = Arr::get($data, 'token');

        if (! is_string($token) || $token === '') {
            $message = Arr::get($data, 'response');

            throw AuthenticationException::rejected(
                self::CERTIFIER,
                is_string($message) ? $message : $response->body(),
            );
        }

        $this->tokens->put($this->config, $token, $this->parseExpiration(Arr::get($data, 'expira_en')));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send(fn (string $token): Response => $this->authorized($token, $query)->get($path));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function post(string $path, array $body, array $query = []): array
    {
        return $this->send(fn (string $token): Response => $this->authorized($token, $query)->post($path, $body));
    }

    /**
     * @param  callable(string): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $response = $this->dispatch(fn (): Response => $call($this->tokens->get($this->config) ?? $this->login()));

        if ($response->status() === 401) {
            $this->tokens->forget($this->config);

            $response = $this->dispatch(fn (): Response => $call($this->login()));

            if ($response->status() === 401) {
                throw AuthenticationException::rejected(self::CERTIFIER, $response->body());
            }
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw CertifierException::unreadable(self::CERTIFIER, $response->status(), $response->body());
        }

        return $data;
    }

    /**
     * @param  callable(): Response  $call
     */
    private function dispatch(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $exception) {
            throw CertifierException::unreachable(self::CERTIFIER, $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function authorized(string $token, array $query): PendingRequest
    {
        return $this->request()
            ->withHeaders(['Authorization' => $token])
            ->withQueryParameters($query);
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->config->baseUrl)
            ->acceptJson()
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout);
    }

    private function parseExpiration(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat(self::EXPIRATION_FORMAT, $value, self::EXPIRATION_TIMEZONE);
        } catch (Throwable) {
            return null;
        }

        return $parsed instanceof CarbonImmutable ? $parsed : null;
    }
}

<?php

namespace Kakaw\Certifiers\Digifact;

final class DigifactResponse
{
    /** @var array<string, mixed> */
    private readonly array $normalized;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly array $data)
    {
        $this->normalized = array_change_key_case($data);
    }

    public function get(string $key): mixed
    {
        return $this->normalized[mb_strtolower($key)] ?? null;
    }

    public function string(string $key): ?string
    {
        $value = $this->get($key);

        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    public function succeeded(string $codeKey): bool
    {
        return $this->string($codeKey) === '1';
    }

    public function first(string $listKey): ?self
    {
        $list = $this->get($listKey);

        if (! is_array($list) || $list === []) {
            return null;
        }

        $first = reset($list);

        return is_array($first) ? new self($first) : null;
    }

    public function decoded(string $key): ?string
    {
        $value = $this->string($key);

        if ($value === null) {
            return null;
        }

        $decoded = base64_decode($value, true);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }
}

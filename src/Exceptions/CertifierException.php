<?php

namespace Kakaw\Exceptions;

use Illuminate\Support\Str;
use Throwable;

class CertifierException extends KakawException
{
    /**
     * @param  array<string, mixed>  $response
     */
    final public function __construct(
        string $message,
        public readonly string $certifier,
        public readonly ?string $certifierCode = null,
        public readonly array $response = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function rejected(string $certifier, string $code, string $message, array $response): self
    {
        return new self("The [{$certifier}] certifier rejected the request ({$code}): {$message}", $certifier, $code, $response);
    }

    public static function unreadable(string $certifier, int $status, string $body): self
    {
        $excerpt = Str::limit($body, 500);

        return new self("The [{$certifier}] certifier returned an unreadable response (HTTP {$status}): {$excerpt}", $certifier);
    }

    public static function unreachable(string $certifier, Throwable $previous): self
    {
        return new self("The [{$certifier}] certifier could not be reached: {$previous->getMessage()}", $certifier, previous: $previous);
    }
}

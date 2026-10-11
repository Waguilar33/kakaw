<?php

namespace Kakaw\Exceptions;

use Illuminate\Support\Collection;

class InvalidConfigurationException extends KakawException
{
    /**
     * @param  array<int, string>  $keys
     */
    public static function missing(string $certifier, array $keys): self
    {
        $missing = Collection::make($keys)->implode(', ');

        return new self("The [{$certifier}] certifier is missing configuration: {$missing}.");
    }

    public static function unknownCertifier(string $name): self
    {
        return new self("Certifier [{$name}] is not configured or its driver is not supported.");
    }
}

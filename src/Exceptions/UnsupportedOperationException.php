<?php

namespace Kakaw\Exceptions;

class UnsupportedOperationException extends KakawException
{
    public static function for(string $certifier, string $operation): self
    {
        return new self("The [{$certifier}] certifier does not support [{$operation}].");
    }
}

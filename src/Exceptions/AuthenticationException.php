<?php

namespace Kakaw\Exceptions;

class AuthenticationException extends KakawException
{
    public static function rejected(string $certifier, string $message): self
    {
        return new self("The [{$certifier}] certifier rejected the credentials: {$message}");
    }
}

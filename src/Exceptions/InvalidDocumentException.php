<?php

namespace Kakaw\Exceptions;

class InvalidDocumentException extends KakawException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}

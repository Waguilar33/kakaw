<?php

namespace Kakaw\Support;

final class Cui
{
    public const int LENGTH = 13;

    public static function normalize(string $cui): string
    {
        return (string) preg_replace('/[\s-]+/u', '', $cui);
    }

    public static function isValid(string $cui): bool
    {
        return preg_match('/^\d{'.self::LENGTH.'}$/', self::normalize($cui)) === 1;
    }
}

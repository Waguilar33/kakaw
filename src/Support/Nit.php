<?php

namespace Kakaw\Support;

use Illuminate\Support\Str;

final class Nit
{
    public const string FINAL_CONSUMER = 'CF';

    public static function normalize(string $taxId): string
    {
        return Str::upper((string) preg_replace('/[\s-]+/u', '', $taxId));
    }

    public static function isFinalConsumer(string $taxId): bool
    {
        return self::normalize($taxId) === self::FINAL_CONSUMER;
    }
}

<?php

namespace Kakaw\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Amount
{
    public const string VAT_DIVISOR = '1.12';

    private const int SCALE = 6;

    public static function of(BigDecimal|int|string $value): BigDecimal
    {
        return BigDecimal::of($value);
    }

    public static function round(BigDecimal $value): BigDecimal
    {
        return $value->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    public static function format(BigDecimal $value): string
    {
        return (string) self::round($value);
    }
}

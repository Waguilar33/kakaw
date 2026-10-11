<?php

use Brick\Math\BigDecimal;
use Kakaw\Support\Amount;

it('formats an integer with six decimals', function () {
    expect(Amount::format(Amount::of(31)))->toBe('31.000000');
});

it('rounds half up at the sixth decimal', function (string $value, string $expected) {
    expect((string) Amount::round(BigDecimal::of($value)))->toBe($expected);
})->with([
    'rounds up on five' => ['1.0000005', '1.000001'],
    'rounds down below five' => ['1.0000004', '1.000000'],
    'long tail' => ['27.678571428', '27.678571'],
]);

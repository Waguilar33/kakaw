<?php

use Kakaw\Support\Cui;

it('normalizes a cui', function () {
    expect(Cui::normalize(' 1234 56789-0101 '))->toBe('1234567890101');
});

it('validates the cui length', function (string $cui, bool $expected) {
    expect(Cui::isValid($cui))->toBe($expected);
})->with([
    'thirteen digits' => ['1234567890101', true],
    'spaced' => ['1234 56789 0101', true],
    'too short' => ['123456789010', false],
    'too long' => ['12345678901011', false],
    'letters' => ['123456789010K', false],
    'empty' => ['', false],
]);

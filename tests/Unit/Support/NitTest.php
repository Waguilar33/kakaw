<?php

use Kakaw\Support\Nit;

it('normalizes a tax id', function () {
    expect(Nit::normalize(' 3843876-k '))->toBe('3843876K');
});

it('leaves the final consumer marker untouched', function () {
    expect(Nit::normalize('CF'))->toBe('CF');
});

it('detects the final consumer', function (string $taxId, bool $expected) {
    expect(Nit::isFinalConsumer($taxId))->toBe($expected);
})->with([
    'upper' => ['CF', true],
    'lower' => ['cf', true],
    'nit' => ['3843876-K', false],
]);

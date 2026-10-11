<?php

use Kakaw\Data\Item;
use Kakaw\Exceptions\InvalidDocumentException;
use Kakaw\Support\Amount;

it('splits a single line into taxable amount and iva', function () {
    $item = new Item('Cacao', 1, 31);

    expect(Amount::format($item->total()))->toBe('31.000000')
        ->and(Amount::format($item->taxableAmount()))->toBe('27.678571')
        ->and(Amount::format($item->vat()))->toBe('3.321429');
});

it('subtracts the discount from the line total', function () {
    $item = new Item('Cacao', 2, 100, discount: 24);

    expect(Amount::format($item->total()))->toBe('176.000000');
});

it('keeps taxable amount plus iva equal to the total', function (string $price) {
    $item = new Item('Cacao', 3, $price);

    expect($item->taxableAmount()->plus($item->vat())->isEqualTo($item->total()))->toBeTrue();
})->with(['0.01', '31', '123.45', '999999.99']);

it('rejects a discount larger than the line', function () {
    new Item('Cacao', 1, 10, discount: 11);
})->throws(InvalidDocumentException::class);

it('rejects invalid negative values', function (int|string $quantity, int|string $price, int|string $discount) {
    new Item('Cacao', $quantity, $price, discount: $discount);
})->with([
    'quantity' => [-1, 10, 0],
    'price' => [1, -10, 0],
    'discount' => [1, 10, -1],
])->throws(InvalidDocumentException::class);

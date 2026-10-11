<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Kakaw\Data\Document;
use Kakaw\Data\Issuer;
use Kakaw\Data\Item;
use Kakaw\Data\Receiver;
use Kakaw\Exceptions\InvalidDocumentException;
use Kakaw\Support\Amount;

function documentIssuer(): Issuer
{
    return Issuer::fromArray([
        'tax_id' => '1234567-8',
        'name' => 'Empresa Ejemplo, S.A.',
        'commercial_name' => 'Empresa Ejemplo',
        'address' => [
            'street' => 'Calle 1',
            'postal_code' => '01001',
            'municipality' => 'Guatemala',
            'department' => 'Guatemala',
        ],
    ]);
}

function documentFor(Receiver $receiver, Collection $items, ?CarbonImmutable $issuedAt = null): Document
{
    return new Document(documentIssuer(), $receiver, $items, $issuedAt);
}

it('sums totals and iva across lines', function () {
    $items = collect([new Item('A', 1, 31), new Item('B', 2, '10.50')]);
    $document = documentFor(Receiver::finalConsumer(), $items);

    expect(Amount::format($document->total()))->toBe('52.000000')
        ->and($document->vat()->isEqualTo($items->first()->vat()->plus($items->last()->vat())))->toBeTrue();
});

it('rejects a document without items', function () {
    documentFor(Receiver::finalConsumer(), collect());
})->throws(InvalidDocumentException::class);

it('rejects a member that is not an item', function () {
    documentFor(Receiver::finalConsumer(), collect([new Item('A', 1, 10), 'nope']));
})->throws(InvalidDocumentException::class);

it('accepts a final consumer document just under the limit', function () {
    $document = documentFor(Receiver::finalConsumer(), collect([new Item('A', 1, '2499.99')]));

    expect(Amount::format($document->total()))->toBe('2499.990000');
});

it('rejects a final consumer document at the limit', function () {
    documentFor(Receiver::finalConsumer(), collect([new Item('A', 1, 2500)]));
})->throws(InvalidDocumentException::class);

it('accepts a large document to a nit receiver', function () {
    $document = documentFor(new Receiver('3843876-K', 'Cliente'), collect([new Item('A', 1, 5000)]));

    expect(Amount::format($document->total()))->toBe('5000.000000');
});

it('exposes the issue time in guatemala time', function () {
    $document = documentFor(
        Receiver::finalConsumer(),
        collect([new Item('A', 1, 10)]),
        CarbonImmutable::parse('2026-01-01 02:00:00', 'UTC'),
    );

    expect($document->issuedAt->timezoneName)->toBe('America/Guatemala')
        ->and($document->issuedAt->format('Y-m-d H:i'))->toBe('2025-12-31 20:00');
});

<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Kakaw\Certifiers\Digifact\NucDocument;
use Kakaw\Data\Address;
use Kakaw\Data\Document;
use Kakaw\Data\Issuer;
use Kakaw\Data\Item;
use Kakaw\Data\Phrase;
use Kakaw\Data\Receiver;
use Kakaw\Enums\ItemType;

function nucAddress(): Address
{
    return new Address('4 AVENIDA 15-70', '01010', 'Guatemala', 'Guatemala');
}

function nucIssuer(?Collection $phrases = null): Issuer
{
    return new Issuer('123456-7', 'FEL TEST', 'ESTABLECIMIENTO DE PRUEBA', nucAddress(), phrases: $phrases);
}

function nucDocument(?Receiver $receiver = null, ?Collection $items = null, ?Issuer $issuer = null): Document
{
    return new Document(
        issuer: $issuer ?? nucIssuer(),
        receiver: $receiver ?? new Receiver('CF', 'CONSUMIDOR FINAL', nucAddress()),
        items: $items ?? collect([
            new Item('prueba', 1, 31, ItemType::Service, 'SER'),
            new Item('Jeans', 1, 600),
        ]),
        issuedAt: CarbonImmutable::parse('2022-09-05 11:28:00', 'America/Guatemala'),
    );
}

function nucFixture(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/digifact/fact-cf.json'), true);
}

it('emits exactly the top-level keys', function () {
    expect(array_keys(NucDocument::from(nucDocument())))->toBe([
        'Version', 'CountryCode', 'Header', 'Seller', 'Buyer', 'Items', 'Totals', 'AdditionalDocumentInfo',
    ]);
});

it('only emits keys present in the digifact example', function (string $path) {
    $payload = NucDocument::from(nucDocument());
    $example = nucFixture();

    $emitted = array_keys(Arr::get($payload, $path));
    $known = array_keys(Arr::get($example, $path));

    expect(array_diff($emitted, $known))->toBe([]);
})->with([
    'Header', 'Seller', 'Seller.BranchInfo.AddressInfo', 'Buyer', 'Items.0', 'Items.0.Taxes.Tax.0', 'Totals',
]);

it('formats amounts as six decimal strings', function () {
    $payload = NucDocument::from(nucDocument());

    expect($payload['Items'][0]['Price'])->toBe('31.000000')
        ->and($payload['Items'][0]['Qty'])->toBe('1.000000')
        ->and($payload['Totals']['GrandTotal']['InvoiceTotal'])->toBe('631.000000');
});

it('splits each item total into taxable amount and vat', function () {
    $payload = NucDocument::from(nucDocument());
    $tax = $payload['Items'][0]['Taxes']['Tax'][0];

    expect($tax['TaxableAmount'])->toBe('27.678571')
        ->and($tax['Amount'])->toBe('3.321429')
        ->and(bcadd($tax['TaxableAmount'], $tax['Amount'], 6))->toBe($payload['Items'][0]['Totals']['TotalItem']);
});

it('totals the document as the sum of the items', function () {
    $payload = NucDocument::from(nucDocument());
    $sum = collect($payload['Items'])->reduce(fn (string $carry, array $item): string => bcadd($carry, $item['Totals']['TotalItem'], 6), '0');

    expect($payload['Totals']['GrandTotal']['InvoiceTotal'])->toBe($sum);
});

it('numbers the phrase entries', function () {
    $issuer = nucIssuer(collect([new Phrase(1, 1), new Phrase(2, 4)]));
    $info = NucDocument::from(nucDocument(issuer: $issuer))['Seller']['AdditionlInfo'];

    expect($info)->toHaveCount(4)
        ->and(array_column($info, 'Data'))->toBe(['1', '1', '2', '2'])
        ->and(array_column($info, 'Name'))->toBe(['TipoFrase', 'Escenario', 'TipoFrase', 'Escenario'])
        ->and(array_column($info, 'Value'))->toBe(['1', '1', '2', '4']);
});

it('omits the buyer address when the receiver has none', function () {
    $payload = NucDocument::from(nucDocument(new Receiver('CF', 'CONSUMIDOR FINAL')));

    expect($payload['Buyer'])->not->toHaveKey('AddressInfo');
});

it('marks a buyer identified by cui', function () {
    $nuc = NucDocument::from(nucDocument(Receiver::withCui('1234567890101', 'Juan Perez')));

    expect($nuc['Buyer'])->toBe(['TaxID' => '1234567890101', 'TaxIDType' => 'CUI', 'Name' => 'Juan Perez']);
});

it('sends no id type for a buyer identified by nit', function () {
    expect(NucDocument::from(nucDocument(new Receiver('1234567-8', 'Cliente')))['Buyer'])->not->toHaveKey('TaxIDType');
});

it('maps item discounts', function () {
    $items = collect([new Item('con descuento', 1, 100, discount: 10), new Item('sin descuento', 1, 100)]);
    $payload = NucDocument::from(nucDocument(items: $items));

    expect($payload['Items'][0]['Discounts']['Discount'][0]['Amount'])->toBe('10.000000')
        ->and($payload['Items'][1]['Discounts'])->toBeNull();
});

it('maps item types to digifact words', function () {
    $payload = NucDocument::from(nucDocument());

    expect($payload['Items'][0]['Type'])->toBe('Servicio')
        ->and($payload['Items'][1]['Type'])->toBe('Bien')
        ->and($payload['Items'][1]['Number'])->toBe('2');
});

it('adds one adenda with a fresh upper-case uuid', function () {
    $first = NucDocument::from(nucDocument())['AdditionalDocumentInfo']['AdditionalInfo'];
    $second = NucDocument::from(nucDocument())['AdditionalDocumentInfo']['AdditionalInfo'];

    expect($first)->toHaveCount(1)
        ->and($first[0]['Type'])->toBe('ADENDA')
        ->and($first[0]['Code'])->toHaveLength(36)
        ->and($first[0]['Code'])->toBe(strtoupper($first[0]['Code']))
        ->and($first[0]['Code'])->not->toBe($second[0]['Code'])
        ->and($first[0]['AditionalInfo'][0]['Value'])->toBe('NO_VALIDAR');
});

it('formats the issue time with the guatemala offset', function () {
    expect(NucDocument::from(nucDocument())['Header']['IssuedDateTime'])->toBe('2022-09-05T11:28:00-06:00');
});

it('maps seller and header values', function () {
    $payload = NucDocument::from(nucDocument());

    expect($payload['Seller']['TaxID'])->toBe('1234567')
        ->and($payload['Seller']['TaxIDAdditionalInfo'][0]['Value'])->toBe('GEN')
        ->and($payload['Seller']['BranchInfo']['AddressInfo']['City'])->toBe('01010')
        ->and($payload['Header']['DocType'])->toBe('FACT')
        ->and($payload['CountryCode'])->toBe('GT');
});

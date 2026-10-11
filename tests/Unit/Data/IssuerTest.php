<?php

use Kakaw\Data\Issuer;
use Kakaw\Data\Phrase;
use Kakaw\Data\Receiver;
use Kakaw\Enums\DocumentType;
use Kakaw\Enums\VatAffiliation;
use Kakaw\Exceptions\InvalidDocumentException;

function issuerConfig(): array
{
    return [
        'tax_id' => '1234567-8',
        'name' => 'Empresa Ejemplo, S.A.',
        'commercial_name' => 'Empresa Ejemplo',
        'establishment_code' => '1',
        'vat_affiliation' => 'GEN',
        'address' => [
            'street' => 'Calle 1',
            'postal_code' => '01001',
            'municipality' => 'Guatemala',
            'department' => 'Guatemala',
            'country' => 'GT',
        ],
        'phrases' => [
            ['type' => 1, 'scenario' => 1],
        ],
    ];
}

it('builds an issuer from the config shape', function () {
    $issuer = Issuer::fromArray(issuerConfig());

    expect($issuer->taxId)->toBe('12345678')
        ->and($issuer->vatAffiliation)->toBe(VatAffiliation::General)
        ->and($issuer->address->postalCode)->toBe('01001')
        ->and($issuer->phrases)->toHaveCount(1)
        ->and($issuer->phrases->first())->toEqual(new Phrase(1, 1));
});

it('defaults to a single phrase when none are given', function () {
    $config = issuerConfig();
    unset($config['phrases']);

    $issuer = Issuer::fromArray($config);

    expect($issuer->phrases)->toHaveCount(1)
        ->and($issuer->phrases->first())->toEqual(new Phrase(1, 1));
});

it('names the first missing required value', function () {
    $config = issuerConfig();
    unset($config['tax_id']);

    Issuer::fromArray($config);
})->throws(InvalidDocumentException::class, 'tax_id');

it('names a missing nested value', function () {
    $config = issuerConfig();
    $config['address']['street'] = null;

    Issuer::fromArray($config);
})->throws(InvalidDocumentException::class, 'address.street');

it('reports the final consumer receiver', function () {
    $receiver = Receiver::finalConsumer();

    expect($receiver->isFinalConsumer())->toBeTrue()
        ->and($receiver->taxId)->toBe('CF')
        ->and($receiver->name)->toBe('Consumidor Final');
});

it('has only the invoice document type', function () {
    expect(DocumentType::cases())->toHaveCount(1);
});

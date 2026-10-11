<?php

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Kakaw\Data\Results\AnnulledDocument;
use Kakaw\Data\Results\CertifiedDocument;
use Kakaw\Data\Results\RetrievedDocument;
use Kakaw\Data\Results\Taxpayer;

it('declares every result object final and readonly', function (string $class) {
    $reflection = new ReflectionClass($class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
})->with([
    'certified document' => CertifiedDocument::class,
    'annulled document' => AnnulledDocument::class,
    'taxpayer' => Taxpayer::class,
    'retrieved document' => RetrievedDocument::class,
]);

it('exposes every certified document constructor argument under the same property name', function () {
    $reflection = new ReflectionClass(CertifiedDocument::class);

    $parameterNames = array_map(
        fn (ReflectionParameter $parameter): string => $parameter->getName(),
        $reflection->getConstructor()->getParameters(),
    );

    expect($parameterNames)->toBe([
        'authorization',
        'series',
        'number',
        'issuedAt',
        'certifiedAt',
        'issuerTaxId',
        'issuerName',
        'receiverTaxId',
        'receiverName',
        'total',
        'xml',
        'raw',
    ]);

    $document = new CertifiedDocument(
        authorization: 'AUTH-1',
        series: 'A',
        number: '42',
        issuedAt: CarbonImmutable::parse('2026-10-01 10:00:00'),
        certifiedAt: null,
        issuerTaxId: 'ISSUER-TAX',
        issuerName: null,
        receiverTaxId: null,
        receiverName: null,
        total: BigDecimal::of('125.50'),
        xml: '<xml/>',
        raw: ['status' => 'ok'],
    );

    foreach ($parameterNames as $name) {
        expect(property_exists($document, $name))->toBeTrue();
    }

    expect($document->authorization)->toBe('AUTH-1')
        ->and($document->series)->toBe('A')
        ->and($document->number)->toBe('42')
        ->and($document->issuedAt->toDateTimeString())->toBe('2026-10-01 10:00:00')
        ->and($document->certifiedAt)->toBeNull()
        ->and($document->issuerTaxId)->toBe('ISSUER-TAX')
        ->and($document->issuerName)->toBeNull()
        ->and($document->receiverTaxId)->toBeNull()
        ->and($document->receiverName)->toBeNull()
        ->and((string) $document->total)->toBe('125.50')
        ->and($document->xml)->toBe('<xml/>')
        ->and($document->raw)->toBe(['status' => 'ok']);
});

it('keeps the taxpayer immutable after construction', function () {
    $taxpayer = new Taxpayer(taxId: 'TAX-1', name: 'Acme');

    expect(fn () => $taxpayer->name = 'Other')->toThrow(Error::class);
});

it('keeps the annulled and retrieved documents nullable where the spec allows', function () {
    $annulled = new AnnulledDocument(
        authorization: 'AUTH-2',
        series: null,
        number: null,
        xml: null,
        raw: [],
    );

    $retrieved = new RetrievedDocument(authorization: 'AUTH-3', xml: '<xml/>');

    expect($annulled->series)->toBeNull()
        ->and($annulled->number)->toBeNull()
        ->and($annulled->xml)->toBeNull()
        ->and($retrieved->authorization)->toBe('AUTH-3')
        ->and($retrieved->xml)->toBe('<xml/>');
});

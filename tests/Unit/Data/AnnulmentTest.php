<?php

use Carbon\CarbonImmutable;
use Kakaw\Data\Annulment;
use Kakaw\Data\TotalCreditNote;

it('converts the annulment issue time to guatemala time', function () {
    $annulment = new Annulment(
        'AUTH-1',
        '1234567-8',
        'cf',
        CarbonImmutable::parse('2026-01-01 02:00:00', 'UTC'),
        'Duplicate',
    );

    expect($annulment->issuedAt->timezoneName)->toBe('America/Guatemala')
        ->and($annulment->issuedAt->format('Y-m-d H:i'))->toBe('2025-12-31 20:00')
        ->and($annulment->issuerTaxId)->toBe('12345678')
        ->and($annulment->receiverTaxId)->toBe('CF');
});

it('converts the total credit note time to guatemala time', function () {
    $note = new TotalCreditNote('AUTH-1', '1234567-8', 'Return', CarbonImmutable::parse('2026-01-01 02:00:00', 'UTC'));

    expect($note->issuedAt->timezoneName)->toBe('America/Guatemala')
        ->and($note->issuedAt->format('Y-m-d H:i'))->toBe('2025-12-31 20:00')
        ->and($note->reference)->toBeNull();
});

it('defaults the total credit note time to now', function () {
    $note = new TotalCreditNote('AUTH-1', '1234567-8', 'Return');

    expect($note->issuedAt->timezoneName)->toBe('America/Guatemala');
});

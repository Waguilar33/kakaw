<?php

use Kakaw\Data\Receiver;
use Kakaw\Enums\IdentificationType;
use Kakaw\Exceptions\InvalidDocumentException;

it('identifies a receiver by nit unless told otherwise', function () {
    $receiver = new Receiver('1234567-8', 'Cliente');

    expect($receiver->taxId)->toBe('12345678')
        ->and($receiver->identificationType)->toBe(IdentificationType::Nit)
        ->and($receiver->isFinalConsumer())->toBeFalse();
});

it('builds a receiver identified by cui', function () {
    $receiver = Receiver::withCui('1234 56789 0101', 'Juan Perez');

    expect($receiver->taxId)->toBe('1234567890101')
        ->and($receiver->identificationType)->toBe(IdentificationType::Cui)
        ->and($receiver->address)->toBeNull()
        ->and($receiver->isFinalConsumer())->toBeFalse();
});

it('refuses a cui that is not thirteen digits', function (string $cui) {
    Receiver::withCui($cui, 'Juan Perez');
})->with(['123456789010', 'CF', '1234567-8'])->throws(InvalidDocumentException::class, 'A CUI must have exactly 13 digits.');

it('builds the final consumer', function () {
    expect(Receiver::finalConsumer()->isFinalConsumer())->toBeTrue();
});

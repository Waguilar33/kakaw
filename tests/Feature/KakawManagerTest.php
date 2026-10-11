<?php

use Illuminate\Support\Collection;
use Kakaw\Data\Document;
use Kakaw\Data\Issuer;
use Kakaw\Data\Item;
use Kakaw\Data\Receiver;
use Kakaw\Data\Results\CertifiedDocument;
use Kakaw\Data\TotalCreditNote;
use Kakaw\Exceptions\InvalidConfigurationException;
use Kakaw\Exceptions\UnsupportedOperationException;
use Kakaw\Facades\Kakaw;
use Kakaw\Tests\Fixtures\StubCertifier;

function managerIssuerConfig(): array
{
    return [
        'tax_id' => '1234567-8',
        'name' => 'Empresa Ejemplo S.A.',
        'commercial_name' => 'Empresa Ejemplo',
        'address' => [
            'street' => '1a calle',
            'postal_code' => '01001',
            'municipality' => 'Guatemala',
            'department' => 'Guatemala',
        ],
    ];
}

beforeEach(function () {
    Kakaw::extend('stub', fn ($app, array $config = []) => new StubCertifier);

    config()->set('kakaw.default', 'stub');
    config()->set('kakaw.certifiers.stub', ['driver' => 'stub']);
    config()->set('kakaw.issuer', managerIssuerConfig());
});

function stubDocument(): Document
{
    return new Document(
        issuer: Kakaw::issuer(),
        receiver: Receiver::finalConsumer(),
        items: Collection::make([new Item('Chocolate', 1, 10)]),
    );
}

it('reaches the default certifier and returns its certified document', function () {
    $result = Kakaw::certify(stubDocument());

    expect($result)->toBeInstanceOf(CertifiedDocument::class)
        ->and($result->authorization)->toBe('AUTH-1')
        ->and(Kakaw::certifier()->calls)->toBe(['certify']);
});

it('returns the same certifier instance on repeated calls', function () {
    expect(Kakaw::certifier('stub'))->toBe(Kakaw::certifier('stub'));
});

it('throws for a certifier that is not configured', function () {
    Kakaw::certifier('nope');
})->throws(InvalidConfigurationException::class);

it('builds a new certifier on every call', function () {
    $first = Kakaw::build(['driver' => 'stub']);
    $second = Kakaw::build(['driver' => 'stub']);

    expect($first)->toBeInstanceOf(StubCertifier::class)
        ->and($first)->not->toBe($second);
});

it('passes the supplied config to a custom creator', function () {
    $received = null;

    Kakaw::extend('spy', function ($app, array $config) use (&$received) {
        $received = $config;

        return new StubCertifier;
    });

    Kakaw::build(['driver' => 'spy', 'tax_id' => '123']);

    expect($received)->toBe(['driver' => 'spy', 'tax_id' => '123']);
});

it('throws when building without a driver', function () {
    Kakaw::build([]);
})->throws(InvalidConfigurationException::class);

it('throws when building with an unknown driver', function () {
    Kakaw::build(['driver' => 'nope']);
})->throws(InvalidConfigurationException::class);

it('throws when a configured block has an unknown driver', function () {
    config()->set('kakaw.certifiers.broken', ['driver' => 'unknown']);

    Kakaw::certifier('broken');
})->throws(InvalidConfigurationException::class);

it('throws when a configured block has no driver', function () {
    config()->set('kakaw.certifiers.broken', ['environment' => 'test']);

    Kakaw::certifier('broken');
})->throws(InvalidConfigurationException::class);

it('resolves a named block through its driver key', function () {
    config()->set('kakaw.certifiers.company-b', ['driver' => 'stub']);

    expect(Kakaw::certifier('company-b'))->toBeInstanceOf(StubCertifier::class)
        ->and(Kakaw::certifier('company-b'))->not->toBe(Kakaw::certifier('stub'));
});

it('rejects credit notes when the default certifier does not support them', function () {
    Kakaw::creditNote(new TotalCreditNote('AUTH-1', '1234567', 'Return'));
})->throws(UnsupportedOperationException::class);

it('builds the issuer from configuration', function () {
    config()->set('kakaw.issuer', managerIssuerConfig());

    $issuer = Kakaw::issuer();

    expect($issuer)->toBeInstanceOf(Issuer::class)
        ->and($issuer->taxId)->toBe('12345678')
        ->and($issuer->name)->toBe('Empresa Ejemplo S.A.');
});

it('forwards other calls to the default certifier', function () {
    Kakaw::verifyCredentials();

    expect(Kakaw::certifier()->calls)->toBe(['verifyCredentials']);
});

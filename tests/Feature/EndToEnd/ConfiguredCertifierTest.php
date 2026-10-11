<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kakaw\Data\Address;
use Kakaw\Data\Annulment;
use Kakaw\Data\Document;
use Kakaw\Data\Item;
use Kakaw\Data\Receiver;
use Kakaw\Data\Results\AnnulledDocument;
use Kakaw\Data\Results\CertifiedDocument;
use Kakaw\Data\Results\RetrievedDocument;
use Kakaw\Data\Results\Taxpayer;
use Kakaw\Data\TotalCreditNote;
use Kakaw\Exceptions\AuthenticationException;
use Kakaw\Exceptions\CertifierException;
use Kakaw\Exceptions\InvalidConfigurationException;
use Kakaw\Exceptions\InvalidDocumentException;
use Kakaw\Facades\Kakaw;

function e2eCredentials(): array
{
    return [
        'driver' => 'digifact',
        'environment' => 'test',
        'tax_id' => '1234567-8',
        'username' => 'APIUSER',
        'password' => 'secret',
    ];
}

function e2eConfigureCertifier(): void
{
    config()->set('kakaw.certifiers.digifact', e2eCredentials());
}

function e2eDocument(?Receiver $receiver = null, int|string $price = 31): Document
{
    return new Document(
        issuer: Kakaw::issuer(),
        receiver: $receiver ?? Receiver::finalConsumer(),
        items: collect([new Item('prueba', 1, $price)]),
        issuedAt: CarbonImmutable::parse('2026-03-01 10:00:00', 'America/Guatemala'),
    );
}

function e2eConfigureIssuer(): void
{
    config()->set('kakaw.issuer', [
        'tax_id' => '1234567-8',
        'name' => 'FEL TEST',
        'commercial_name' => 'TIENDA',
        'address' => [
            'street' => 'CALLE 1',
            'postal_code' => '01010',
            'municipality' => 'Guatemala',
            'department' => 'Guatemala',
            'country' => 'GT',
        ],
    ]);
}

function e2eFixture(string $name): array
{
    return json_decode(test()->fixture("digifact/responses/{$name}.json"), true);
}

function e2eFakeSuccess(): void
{
    Http::fake([
        '*/login/get_token' => Http::response(e2eFixture('login')),
        '*/v2/transform/nuc_json*' => Http::response(e2eFixture('certify-success')),
        '*/CancelFelGT' => Http::response(e2eFixture('annul-success')),
        '*/cert/ncredtotal' => Http::response(e2eFixture('certify-success')),
        '*/Shared*' => Http::response(e2eFixture('taxpayer-found')),
        '*/GetDocument*' => Http::response(e2eFixture('document-found')),
    ]);
}

beforeEach(function () {
    e2eConfigureIssuer();
});

it('certifies with config credentials using one login and one certify request', function () {
    e2eConfigureCertifier();
    e2eFakeSuccess();

    $result = Kakaw::certify(e2eDocument());

    expect($result)->toBeInstanceOf(CertifiedDocument::class)
        ->and($result->authorization)->toBe('5A1C2B3D-4E5F-4A6B-8C7D-9E0F1A2B3C4D')
        ->and($result->xml)->toBe('<dte>ok</dte>');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/login/get_token'));
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v2/transform/nuc_json'));
    expect(Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/login/get_token')))->toHaveCount(1);
});

it('logs in only once across certify and taxpayer', function () {
    e2eConfigureCertifier();
    e2eFakeSuccess();

    Kakaw::certify(e2eDocument());
    $taxpayer = Kakaw::taxpayer('4465394-8');

    expect($taxpayer)->toBeInstanceOf(Taxpayer::class)
        ->and($taxpayer->name)->toBe('Empresa Ejemplo S.A.')
        ->and(Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/login/get_token')))->toHaveCount(1);
});

it('throws an authentication exception with the digifact message when credentials are rejected', function () {
    e2eConfigureCertifier();
    Http::fake(['*/login/get_token' => Http::response(['response' => 'Credenciales invalidas'], 401)]);

    expect(fn () => Kakaw::verifyCredentials())
        ->toThrow(AuthenticationException::class, 'Credenciales invalidas');
});

it('throws an invalid configuration exception naming the missing keys and sends nothing', function () {
    config()->set('kakaw.certifiers.digifact', ['driver' => 'digifact']);
    Http::fake();

    try {
        Kakaw::certify(e2eDocument());
    } catch (InvalidConfigurationException $exception) {
        expect($exception->getMessage())->toContain('tax_id')->toContain('username')->toContain('password');
        Http::assertNothingSent();

        return;
    }

    $this->fail('Expected an InvalidConfigurationException.');
});

it('throws a certifier exception when the certification is rejected', function () {
    e2eConfigureCertifier();
    Http::fake([
        '*/login/get_token' => Http::response(e2eFixture('login')),
        '*/v2/transform/nuc_json*' => Http::response(e2eFixture('certify-rejected')),
    ]);

    expect(fn () => Kakaw::certify(e2eDocument()))
        ->toThrow(CertifierException::class, 'NIT invalido');
});

it('returns result objects for annul, credit note and document through the facade', function () {
    e2eConfigureCertifier();
    e2eFakeSuccess();

    $annulled = Kakaw::annul(new Annulment('AUTH-1', '1234567-8', 'CF', CarbonImmutable::now(), 'Error en datos'));
    $creditNote = Kakaw::creditNote(new TotalCreditNote('AUTH-1', '1234567-8', 'Devolucion'));
    $retrieved = Kakaw::document('AUTH-1');

    expect($annulled)->toBeInstanceOf(AnnulledDocument::class)
        ->and($annulled->number)->toBe('7654321')
        ->and($annulled->xml)->toBe('<anulado/>')
        ->and($creditNote)->toBeInstanceOf(CertifiedDocument::class)
        ->and($retrieved)->toBeInstanceOf(RetrievedDocument::class)
        ->and($retrieved->xml)->toBe('<doc>found</doc>');
});

it('never reaches http for a final consumer document of Q2,500', function () {
    e2eConfigureCertifier();
    e2eFakeSuccess();

    expect(fn () => Kakaw::certify(e2eDocument(price: 2500)))->toThrow(InvalidDocumentException::class);

    Http::assertNothingSent();
});

it('passes the configured issuer straight into a document that certifies', function () {
    e2eConfigureCertifier();
    e2eFakeSuccess();

    $issuer = Kakaw::issuer();
    $document = new Document(
        issuer: $issuer,
        receiver: new Receiver('4465394-8', 'Empresa Ejemplo S.A.', new Address('CALLE 2', '01011', 'Guatemala', 'Guatemala')),
        items: collect([new Item('servicio', 2, 100)]),
    );

    expect(Kakaw::certify($document))->toBeInstanceOf(CertifiedDocument::class);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v2/transform/nuc_json')
        && $request['Seller']['TaxID'] === $issuer->taxId
        && $request['Seller']['Name'] === 'FEL TEST');
});

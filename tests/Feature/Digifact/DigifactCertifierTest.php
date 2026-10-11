<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kakaw\Certifiers\Digifact\DigifactCertifier;
use Kakaw\Certifiers\Digifact\DigifactClient;
use Kakaw\Certifiers\Digifact\DigifactConfig;
use Kakaw\Certifiers\Digifact\TokenStore;
use Kakaw\Contracts\Certifier;
use Kakaw\Contracts\IssuesTotalCreditNotes;
use Kakaw\Data\Address;
use Kakaw\Data\Annulment;
use Kakaw\Data\Document;
use Kakaw\Data\Issuer;
use Kakaw\Data\Item;
use Kakaw\Data\Receiver;
use Kakaw\Data\Results\AnnulledDocument;
use Kakaw\Data\Results\CertifiedDocument;
use Kakaw\Data\Results\RetrievedDocument;
use Kakaw\Data\Results\Taxpayer;
use Kakaw\Data\TotalCreditNote;
use Kakaw\Exceptions\CertifierException;

function certifierConfig(): DigifactConfig
{
    return DigifactConfig::fromArray([
        'tax_id' => '1234567-8',
        'username' => 'APIUSER',
        'password' => 'secret',
        'base_url' => 'https://digifact.test/api/',
    ]);
}

function certifierUnderTest(bool $cachedToken = true): DigifactCertifier
{
    $store = new TokenStore(app('cache')->store());

    if ($cachedToken) {
        $store->put(certifierConfig(), 'cached', null);
    }

    return new DigifactCertifier(new DigifactClient(app(Factory::class), $store, certifierConfig()), certifierConfig());
}

function certifierDocument(): Document
{
    return new Document(
        issuer: new Issuer('1234567-8', 'FEL TEST', 'TIENDA', new Address('CALLE 1', '01010', 'Guatemala', 'Guatemala')),
        receiver: Receiver::finalConsumer(),
        items: collect([new Item('prueba', 1, 31)]),
        issuedAt: CarbonImmutable::parse('2026-03-01 10:00:00', 'America/Guatemala'),
    );
}

function certifySuccess(array $overrides = []): array
{
    return array_merge([
        'code' => 1,
        'message' => 'OK',
        'authNumber' => 'AUTH-1',
        'batch' => 'ABC123',
        'serial' => '987',
        'issuedTimeStamp' => '2026-03-01T10:00:05',
        'enrolledTimeStamp' => '2026-03-01T10:00:09',
        'taxID' => '000012345678',
        'name' => 'FEL TEST',
        'receiverTaxID' => 'CF',
        'receiverName' => 'CONSUMIDOR FINAL',
        'totalAmount' => '31.000000',
        'responseData1' => base64_encode('<xml/>'),
    ], $overrides);
}

function certifierCreditNote(?string $reference = null): TotalCreditNote
{
    return new TotalCreditNote(
        'AUTH-1',
        '1234567-8',
        'Devolucion',
        CarbonImmutable::parse('2026-03-02 09:30:15', 'America/Guatemala'),
        $reference,
    );
}

it('implements both capability contracts', function () {
    expect(certifierUnderTest())->toBeInstanceOf(Certifier::class)
        ->toBeInstanceOf(IssuesTotalCreditNotes::class);
});

it('verifies credentials with a fresh login even when a token is cached', function () {
    Http::fake(['*/login/get_token' => Http::response(['Token' => 'fresh'])]);

    certifierUnderTest()->verifyCredentials();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://digifact.test/api/login/get_token');
});

it('certifies against the transform endpoint with the expected query', function () {
    Http::fake(['*/v2/transform/nuc_json*' => Http::response(certifySuccess())]);

    certifierUnderTest()->certify(certifierDocument());

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://digifact.test/api/v2/transform/nuc_json?')
        && str_contains($request->url(), 'TAXID=000012345678')
        && str_contains($request->url(), 'USERNAME=APIUSER')
        && str_contains($request->url(), 'FORMAT=XML')
        && $request['Header']['DocType'] === 'FACT');
});

it('returns a certified document on success', function () {
    Http::fake(['*/v2/transform/nuc_json*' => Http::response(certifySuccess())]);

    $result = certifierUnderTest()->certify(certifierDocument());

    expect($result)->toBeInstanceOf(CertifiedDocument::class)
        ->and($result->authorization)->toBe('AUTH-1')
        ->and($result->series)->toBe('ABC123')
        ->and($result->number)->toBe('987')
        ->and($result->xml)->toBe('<xml/>')
        ->and($result->raw['authNumber'])->toBe('AUTH-1')
        ->and($result->issuedAt->format('Y-m-d H:i:s P'))->toBe('2026-03-01 10:00:05 -06:00')
        ->and($result->certifiedAt?->format('H:i:s'))->toBe('10:00:09')
        ->and($result->issuerTaxId)->toBe('000012345678')
        ->and($result->issuerName)->toBe('FEL TEST')
        ->and($result->receiverTaxId)->toBe('CF')
        ->and($result->receiverName)->toBe('CONSUMIDOR FINAL')
        ->and((string) $result->total)->toBe('31.000000');
});

it('accepts a string success code', function () {
    Http::fake(['*/v2/transform/nuc_json*' => Http::response(certifySuccess(['code' => '1']))]);

    expect(certifierUnderTest()->certify(certifierDocument()))->toBeInstanceOf(CertifiedDocument::class);
});

it('reads response keys regardless of case', function () {
    Http::fake(['*/v2/transform/nuc_json*' => Http::response(['CODE' => 1, 'AUTHNUMBER' => 'A', 'BATCH' => 'B', 'SERIAL' => 'S', 'RESPONSEDATA1' => base64_encode('<x/>')])]);

    expect(certifierUnderTest()->certify(certifierDocument())->authorization)->toBe('A');
});

it('rejects a certification with message and description', function (int $code) {
    Http::fake(['*/v2/transform/nuc_json*' => Http::response(['code' => $code, 'message' => 'Error de validacion', 'description' => 'NIT invalido'])]);

    try {
        certifierUnderTest()->certify(certifierDocument());
    } catch (CertifierException $exception) {
        expect($exception->getMessage())->toContain('Error de validacion')->toContain('NIT invalido')
            ->and($exception->certifierCode)->toBe((string) $code)
            ->and($exception->response['code'])->toBe($code);

        return;
    }

    $this->fail('Expected a CertifierException.');
})->with([3000, 2001]);

it('rejects a successful certification without xml', function () {
    Http::fake(['*/v2/transform/nuc_json*' => Http::response(certifySuccess(['responseData1' => '']))]);

    expect(fn () => certifierUnderTest()->certify(certifierDocument()))->toThrow(CertifierException::class);
});

it('posts the annulment body', function () {
    Http::fake(['*/CancelFelGT' => Http::response(['Codigo' => 1, 'Autorizacion' => 'AUTH-2', 'Serie' => 'S1', 'Numero' => '5'])]);

    certifierUnderTest()->annul(new Annulment('AUTH-1', '1234567-8', '1234567-8', CarbonImmutable::parse('2026-03-01 10:00:00', 'America/Guatemala'), 'Error en datos'));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://digifact.test/api/CancelFelGT'
        && $request['TaxId'] === '12345678'
        && $request['Autorizacion'] === 'AUTH-1'
        && $request['IdReceptor'] === '12345678'
        && $request['FechaEmisionDocumentoAnular'] === '2026-03-01T10:00:00'
        && $request['MotivoAnulacion'] === 'Error en datos'
        && $request['Username'] === 'APIUSER');
});

it('fills the annulment number from an upper-case key', function () {
    Http::fake(['*/CancelFelGT' => Http::response(['CODIGO' => 1, 'AUTORIZACION' => 'AUTH-2', 'SERIE' => 'S1', 'NUMERO' => '5', 'RESPONSEDATA1' => base64_encode('<anulado/>')])]);

    $result = certifierUnderTest()->annul(new Annulment('AUTH-1', '1234567-8', 'CF', CarbonImmutable::now(), 'x'));

    expect($result)->toBeInstanceOf(AnnulledDocument::class)
        ->and($result->authorization)->toBe('AUTH-2')
        ->and($result->series)->toBe('S1')
        ->and($result->number)->toBe('5')
        ->and($result->xml)->toBe('<anulado/>');
});

it('leaves annulment xml null when absent', function () {
    Http::fake(['*/CancelFelGT' => Http::response(['Codigo' => 1, 'Autorizacion' => 'AUTH-2'])]);

    $result = certifierUnderTest()->annul(new Annulment('AUTH-1', '1234567-8', 'CF', CarbonImmutable::now(), 'x'));

    expect($result->xml)->toBeNull()->and($result->number)->toBeNull();
});

it('rejects an annulment with the digifact message', function () {
    Http::fake(['*/CancelFelGT' => Http::response(['Codigo' => 2, 'Mensaje' => 'Documento ya anulado'])]);

    try {
        certifierUnderTest()->annul(new Annulment('AUTH-1', '1234567-8', 'CF', CarbonImmutable::now(), 'x'));
    } catch (CertifierException $exception) {
        expect($exception->getMessage())->toContain('Documento ya anulado')
            ->and($exception->certifierCode)->toBe('2');

        return;
    }

    $this->fail('Expected a CertifierException.');
});

it('looks up a taxpayer with the hyphen-free nit', function () {
    Http::fake(['*/Shared*' => Http::response(['RESPONSE' => [['NIT' => '12345678', 'NOMBRE' => 'JUAN PEREZ']]])]);

    $result = certifierUnderTest()->taxpayer('1234567-8');

    expect($result)->toBeInstanceOf(Taxpayer::class)
        ->and($result->taxId)->toBe('12345678')
        ->and($result->name)->toBe('JUAN PEREZ');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'Country=GT')
        && str_contains($request->url(), 'Username=GT.000012345678.APIUSER')
        && str_contains($request->url(), 'TaxID=000012345678')
        && str_contains($request->url(), 'Data1=SHARED_GETINFONITcom')
        && str_contains($request->url(), 'Data2=NIT%7C12345678'));
});

it('returns null for an unknown taxpayer', function (mixed $response) {
    Http::fake(['*/Shared*' => Http::response($response)]);

    expect(certifierUnderTest()->taxpayer('99999999'))->toBeNull();
})->with([
    'empty list' => [['RESPONSE' => []]],
    'missing key' => [['other' => 1]],
    'false' => [['RESPONSE' => false]],
]);

it('retrieves a document with decoded xml', function () {
    Http::fake(['*/GetDocument*' => Http::response(['RESPONSE' => [['ResponseData1' => base64_encode('<doc/>')]]])]);

    $result = certifierUnderTest()->document('AUTH-1');

    expect($result)->toBeInstanceOf(RetrievedDocument::class)
        ->and($result->authorization)->toBe('AUTH-1')
        ->and($result->xml)->toBe('<doc/>');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'AUTHNUMBER=AUTH-1')
        && str_contains($request->url(), 'Username=GT.000012345678.APIUSER')
        && str_contains($request->url(), 'TaxID=000012345678')
        && str_contains($request->url(), 'FORMAT=XML'));
});

it('returns null when the document is not found', function (mixed $response) {
    Http::fake(['*/GetDocument*' => Http::response($response)]);

    expect(certifierUnderTest()->document('AUTH-1'))->toBeNull();
})->with([
    'empty list' => [['RESPONSE' => []]],
    'empty data' => [['RESPONSE' => [['ResponseData1' => '']]]],
]);

it('posts a total credit note without a reference', function () {
    Http::fake(['*/cert/ncredtotal' => Http::response(certifySuccess())]);

    certifierUnderTest()->creditNote(certifierCreditNote());

    Http::assertSent(fn (Request $request) => $request->url() === 'https://digifact.test/api/cert/ncredtotal'
        && $request['Staxid'] === '12345678'
        && $request['Authnumber'] === 'AUTH-1'
        && $request['FechaEmision'] === '2026-03-02 09:30:15'
        && $request['MotivoAjuste'] === 'Devolucion'
        && $request['Formatos'] === 'xml'
        && $request['Username'] === 'APIUSER'
        && ! array_key_exists('ReferenciaInterna', $request->data()));
});

it('sends the reference when given', function () {
    Http::fake(['*/cert/ncredtotal' => Http::response(certifySuccess())]);

    certifierUnderTest()->creditNote(certifierCreditNote('REF-9'));

    Http::assertSent(fn (Request $request) => $request['ReferenciaInterna'] === 'REF-9');
});

it('returns a certified document for a credit note', function () {
    Http::fake(['*/cert/ncredtotal' => Http::response(certifySuccess())]);

    expect(certifierUnderTest()->creditNote(certifierCreditNote()))->toBeInstanceOf(CertifiedDocument::class);
});

it('accepts the minimal documented credit note response', function () {
    Http::fake(['*/cert/ncredtotal' => Http::response([
        'code' => 1,
        'message' => 'OK',
        'authNumber' => 'AUTH-3',
        'batch' => 'B',
        'serial' => '1',
        'enrolledTimeStamp' => '2026-03-02T09:30:20',
        'responseData1' => base64_encode('<nc/>'),
    ])]);

    $result = certifierUnderTest()->creditNote(certifierCreditNote());

    expect($result->issuedAt->format('Y-m-d H:i:s'))->toBe('2026-03-02 09:30:15')
        ->and($result->issuerName)->toBeNull()
        ->and($result->receiverTaxId)->toBeNull()
        ->and($result->receiverName)->toBeNull()
        ->and($result->total)->toBeNull()
        ->and($result->issuerTaxId)->toBe('1234567-8');
});

it('rejects a failed credit note', function () {
    Http::fake(['*/cert/ncredtotal' => Http::response(['code' => 3000, 'message' => 'No', 'description' => 'Rechazado'])]);

    expect(fn () => certifierUnderTest()->creditNote(certifierCreditNote()))->toThrow(CertifierException::class, 'No: Rechazado');
});

it('rejects a certify success that carries no authorization number', function () {
    Http::fake(['*/v2/transform/nuc_json*' => Http::response(certifySuccess(['authNumber' => '']))]);

    expect(fn () => certifierUnderTest()->certify(certifierDocument()))
        ->toThrow(CertifierException::class, 'no authorization number');
});

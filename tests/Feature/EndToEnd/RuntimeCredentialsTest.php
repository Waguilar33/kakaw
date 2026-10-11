<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kakaw\Data\Results\CertifiedDocument;
use Kakaw\Facades\Kakaw;

function e2eRuntimeCredentials(string $taxId, string $username, array $overrides = []): array
{
    return array_merge([
        'driver' => 'digifact',
        'environment' => 'test',
        'tax_id' => $taxId,
        'username' => $username,
        'password' => 'secret',
    ], $overrides);
}

beforeEach(function () {
    e2eConfigureIssuer();
});

it('certifies with credentials passed to build and none configured', function () {
    config()->set('kakaw.certifiers.digifact', ['driver' => 'digifact']);
    Http::fake([
        '*/login/get_token' => Http::response(e2eFixture('login')),
        '*/v2/transform/nuc_json*' => Http::response(e2eFixture('certify-success')),
    ]);

    $result = Kakaw::build(e2eRuntimeCredentials('1234567-8', 'APIUSER'))->certify(e2eDocument());

    expect($result)->toBeInstanceOf(CertifiedDocument::class);
});

it('keeps two companies side by side with their own tokens', function () {
    Http::fake([
        '*/login/get_token' => fn (Request $request) => Http::response([
            'Token' => str_contains($request['Username'], 'USERA') ? 'token-a' : 'token-b',
            'expira_en' => '2/4/2099 3:53:21 PM',
        ]),
        '*/v2/transform/nuc_json*' => Http::response(e2eFixture('certify-success')),
    ]);

    $companyA = Kakaw::build(e2eRuntimeCredentials('1234567-8', 'USERA'));
    $companyB = Kakaw::build(e2eRuntimeCredentials('4465394-8', 'USERB'));

    $companyA->certify(e2eDocument());
    $companyB->certify(e2eDocument());
    $companyA->certify(e2eDocument());

    expect(Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/login/get_token')))->toHaveCount(2);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'TAXID=000012345678')
        && $request->header('Authorization') === ['token-a']);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'TAXID=000044653948')
        && $request->header('Authorization') === ['token-b']);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'TAXID=000012345678')
        && $request->header('Authorization') !== ['token-a']);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'TAXID=000044653948')
        && $request->header('Authorization') !== ['token-b']);
});

it('sends to the production host when the environment is production', function () {
    Http::fake([
        '*/login/get_token' => Http::response(e2eFixture('login')),
        '*/v2/transform/nuc_json*' => Http::response(e2eFixture('certify-success')),
    ]);

    Kakaw::build(e2eRuntimeCredentials('1234567-8', 'APIUSER', ['environment' => 'production']))
        ->certify(e2eDocument());

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://nucgt.digifact.com/'));
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'testnucgt'));
});

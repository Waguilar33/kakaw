---
name: kakaw-development
description: Build and test FEL electronic invoicing for Guatemala (SAT) with the Kakaw package. Use when certifying an invoice, annulling a document, issuing a total credit note, looking up a NIT, fetching a certified document, or configuring the Digifact certifier.
---

# Kakaw development

## When to use this skill

Use it when working with `Kakaw\Facades\Kakaw`: certifying an invoice, annulling it, issuing a total credit note, verifying certifier credentials, handling Kakaw exceptions, or writing tests for any of these. Kakaw supports invoices (`FACT`) and total credit notes only.

## Building a Document

Amounts are strings, integers or `BigDecimal`, never floats. Prices include IVA. A `Document` to receiver `CF` in GTQ must total less than Q2,500 or the constructor throws `InvalidDocumentException`.

```php
use Kakaw\Data\Document;
use Kakaw\Data\Item;
use Kakaw\Data\Receiver;
use Kakaw\Enums\ItemType;
use Kakaw\Facades\Kakaw;

$document = new Document(
    issuer: Kakaw::issuer(),
    receiver: new Receiver(taxId: '1234567-8', name: 'Cliente, S.A.'),
    items: collect([
        new Item(description: 'Chocolate 70%', quantity: 2, unitPrice: '25.50'),
        new Item(description: 'Envío', quantity: 1, unitPrice: '15.00', type: ItemType::Service, discount: '1.00'),
    ]),
);
```

`Kakaw::issuer()` reads `config('kakaw.issuer')`. Use `Receiver::finalConsumer()` for CF.

## Operations

```php
use Kakaw\Data\Annulment;
use Kakaw\Data\TotalCreditNote;

$certified = Kakaw::certify($document);

Kakaw::annul(new Annulment(
    authorization: $certified->authorization,
    issuerTaxId: '1234567-8',
    receiverTaxId: '1234567-8',
    issuedAt: $certified->issuedAt,
    reason: 'Error en los datos',
));

Kakaw::creditNote(new TotalCreditNote(
    authorization: $certified->authorization,
    issuerTaxId: '1234567-8',
    reason: 'Devolución completa',
));

Kakaw::taxpayer('1234567-8');
Kakaw::taxpayerByCui('1234567890101');
Kakaw::document($certified->authorization);
Kakaw::status($certified->authorization);
```

`certify` and `creditNote` return `CertifiedDocument`; `annul` returns `AnnulledDocument`; `taxpayer`, `taxpayerByCui`, `document` and `status` return `null` when nothing is found.

## Runtime credentials

For one application serving several companies, build a certifier per call. Nothing is stored and tokens stay internal.

```php
$certifier = Kakaw::build([
    'driver' => 'digifact',
    'environment' => 'production',
    'tax_id' => $company->tax_id,
    'username' => $company->certifier_username,
    'password' => $company->certifier_password,
]);

$certifier->certify($document);
```

## Settings screen: save and verify

```php
use Kakaw\Exceptions\AuthenticationException;
use Kakaw\Exceptions\InvalidConfigurationException;

try {
    Kakaw::build($credentials)->verifyCredentials();
} catch (AuthenticationException|InvalidConfigurationException $exception) {
    return back()->withErrors(['credentials' => 'No se pudieron verificar las credenciales.']);
}
```

Save the credentials only after `verifyCredentials()` returns.

## Error handling

All exceptions extend `Kakaw\Exceptions\KakawException`.

- `InvalidDocumentException`: bad document or issuer config, including the CF limit; thrown before any request.
- `InvalidConfigurationException`: missing or unknown certifier block, missing credentials.
- `AuthenticationException`: credentials rejected.
- `CertifierException`: the certifier refused the request, was unreachable, or answered unreadably; read `certifierCode` and `response`.
- `UnsupportedOperationException`: the certifier lacks the ability, such as total credit notes.

## Testing

Fake the HTTP client. Never reach a real certifier.

```php
use Illuminate\Support\Facades\Http;

Http::preventStrayRequests();

Http::fake([
    '*/login/get_token' => Http::response(['Token' => 'token']),
    '*/v2/transform/nuc_json*' => Http::response([
        'code' => 1,
        'authNumber' => 'AUTH-1',
        'batch' => 'ABC123',
        'serial' => '987',
        'taxID' => '000012345678',
        'responseData1' => base64_encode('<xml/>'),
    ]),
]);

expect(Kakaw::certify($document)->authorization)->toBe('AUTH-1');
```

To use a fake certifier, register one with `Kakaw::extend('stub', fn ($app, array $config) => new StubCertifier)` and set `kakaw.default` to `stub`.

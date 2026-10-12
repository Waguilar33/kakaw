# Kakaw

Facturación electrónica FEL para Guatemala (SAT) en Laravel.

Kakaw issues electronic invoices (FEL, regulated by SAT, the Guatemalan tax authority) from a Laravel application. Certifiers are pluggable drivers behind one contract; v1 ships one, Digifact. You build a `Document`, call `Kakaw::certify()`, and get back the same `CertifiedDocument` whichever certifier is configured.

Nothing has been verified against Digifact live yet. See [docs/digifact.md](docs/digifact.md).

## Requirements

- PHP 8.3 or later
- Laravel 12 or 13

## Installation

```bash
composer require waguilar33/kakaw
php artisan vendor:publish --tag=kakaw-config
```

The service provider and the `Kakaw` facade are auto-discovered.

## Configuration

Set the certifier credentials and the issuer (your company) in `.env`:

```dotenv
KAKAW_CERTIFIER=digifact
KAKAW_DIGIFACT_ENVIRONMENT=test
KAKAW_DIGIFACT_TAX_ID=1234567-8
KAKAW_DIGIFACT_USERNAME=your-user
KAKAW_DIGIFACT_PASSWORD=your-password

KAKAW_ISSUER_TAX_ID=1234567-8
KAKAW_ISSUER_NAME="Mi Empresa, S.A."
KAKAW_ISSUER_COMMERCIAL_NAME="Mi Tienda"
KAKAW_ISSUER_ESTABLISHMENT_CODE=1
KAKAW_ISSUER_VAT_AFFILIATION=GEN
KAKAW_ISSUER_STREET="1a calle 1-01 zona 1"
KAKAW_ISSUER_POSTAL_CODE=01001
KAKAW_ISSUER_MUNICIPALITY=Guatemala
KAKAW_ISSUER_DEPARTMENT=Guatemala
```

Other keys: `KAKAW_DIGIFACT_ENVIRONMENT` accepts `test` or `production`; `KAKAW_DIGIFACT_BASE_URL`, `KAKAW_DIGIFACT_TIMEOUT` and `KAKAW_DIGIFACT_CONNECT_TIMEOUT` override the defaults; `KAKAW_CACHE_STORE` picks the cache store that holds tokens. Issuer phrases are set in `config/kakaw.php`.

## Certify an invoice

```php
use Kakaw\Data\Document;
use Kakaw\Data\Item;
use Kakaw\Data\Receiver;
use Kakaw\Enums\ItemType;
use Kakaw\Facades\Kakaw;

$document = new Document(
    issuer: Kakaw::issuer(),
    receiver: Receiver::finalConsumer(),
    items: collect([
        new Item(description: 'Chocolate 70%', quantity: 2, unitPrice: '25.50'),
        new Item(description: 'Envío', quantity: 1, unitPrice: '15.00', type: ItemType::Service),
    ]),
);

$certified = Kakaw::certify($document);

$certified->authorization;
$certified->series;
$certified->number;
$certified->xml;
$certified->total;
```

A receiver with a NIT:

```php
$receiver = new Receiver(taxId: '1234567-8', name: 'Cliente, S.A.');
```

A receiver without a NIT, identified by CUI (the 13-digit DPI number):

```php
$receiver = Receiver::withCui('1234567890101', 'Juan Pérez');
```

`CertifiedDocument` also carries `issuedAt`, `certifiedAt`, `issuerTaxId`, `issuerName`, `receiverTaxId`, `receiverName` and the certifier's decoded response in `raw`.

## Annul a document

```php
use Carbon\CarbonImmutable;
use Kakaw\Data\Annulment;

$annulled = Kakaw::annul(new Annulment(
    authorization: $certified->authorization,
    issuerTaxId: '1234567-8',
    receiverTaxId: 'CF',
    issuedAt: $certified->issuedAt,
    reason: 'Error en los datos del cliente',
));
```

`issuedAt` is the issue date of the document being annulled. Any deadline for annulment is the application's concern.

## Issue a total credit note

```php
use Kakaw\Data\TotalCreditNote;

$creditNote = Kakaw::creditNote(new TotalCreditNote(
    authorization: $certified->authorization,
    issuerTaxId: '1234567-8',
    reason: 'Devolución completa',
    reference: 'ORDEN-1001',
));
```

The result is a `CertifiedDocument`. If the configured certifier cannot issue total credit notes, `UnsupportedOperationException` is thrown.

## Look up a taxpayer and fetch a document

```php
$taxpayer = Kakaw::taxpayer('1234567-8');

$taxpayer?->name;

$person = Kakaw::taxpayerByCui('1234567890101');

$retrieved = Kakaw::document($certified->authorization);

$retrieved?->xml;
```

Both return `null` when the certifier finds nothing.

## Credentials at runtime

For applications that hold credentials per company, build a certifier from an array instead of reading `config/kakaw.php`. Nothing is stored; each call returns a fresh certifier.

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

A settings screen can prove credentials when the user saves them. `verifyCredentials()` forces a fresh login and throws `AuthenticationException` when the certifier rejects them:

```php
use Kakaw\Exceptions\AuthenticationException;

try {
    Kakaw::build($credentials)->verifyCredentials();
} catch (AuthenticationException $exception) {
    return back()->withErrors(['password' => 'Credenciales rechazadas por el certificador.']);
}
```

Several named blocks under `kakaw.certifiers` can share one driver; pick one with `Kakaw::certifier('company-b')`.

Tokens are handled for you. They are cached per environment, NIT and username, and the driver logs in again by itself when a token expires.

## Errors

All exceptions extend `Kakaw\Exceptions\KakawException`.

| Exception | Thrown when |
|---|---|
| `InvalidDocumentException` | A `Document` or the issuer config is invalid: no items, a negative amount, a discount above the line amount, a missing issuer value, or a CF document at or above Q2,500. |
| `InvalidConfigurationException` | A certifier block is missing, has an unknown or missing `driver`, lacks required credentials, or has an invalid value. |
| `AuthenticationException` | The certifier rejects the credentials. |
| `CertifierException` | The certifier rejects a request, is unreachable, or returns an unreadable response. It carries `certifier`, `certifierCode` and `response`. The certifier's own message is passed through untouched. |
| `UnsupportedOperationException` | The configured certifier does not support the operation, such as total credit notes. |

```php
use Kakaw\Exceptions\CertifierException;
use Kakaw\Exceptions\InvalidDocumentException;

try {
    $certified = Kakaw::certify($document);
} catch (CertifierException $exception) {
    report($exception);
    $exception->certifierCode;
}
```

`InvalidDocumentException` is thrown when the `Document` is constructed, before any request is made.

A timeout surfaces as `CertifierException`, and it does not mean the document was not certified: the request may have reached the certifier. Check with the certifier before sending the same document again. The default timeout is 300 seconds for that reason.

## Money

- Item prices include IVA.
- Amounts are strings, integers or `Brick\Math\BigDecimal`. Floats are not accepted.
- The line total is unit price times quantity minus the discount. The taxable amount is the total divided by 1.12, and IVA is the total minus the taxable amount. All of them go on the wire with six decimals.

## Final consumer limit

A document to receiver `CF` in GTQ must total less than Q2,500.00 (Acuerdo Gubernativo 12-2023, numeral 9). Building such a `Document` throws `InvalidDocumentException`. For a larger amount, identify the buyer with a NIT.

## Testing

Every request goes through Laravel's HTTP client, so `Http::fake` covers it. Fake the login and the operation:

```php
use Illuminate\Support\Facades\Http;

Http::preventStrayRequests();

Http::fake([
    '*/login/get_token' => Http::response(['Token' => 'token', 'expira_en' => '10/12/2026 4:00:00 PM']),
    '*/v2/transform/nuc_json*' => Http::response([
        'code' => 1,
        'message' => 'OK',
        'authNumber' => 'AUTH-1',
        'batch' => 'ABC123',
        'serial' => '987',
        'issuedTimeStamp' => '2026-10-12T10:00:05',
        'taxID' => '000012345678',
        'totalAmount' => '66.000000',
        'responseData1' => base64_encode('<xml/>'),
    ]),
]);

expect(Kakaw::certify($document)->authorization)->toBe('AUTH-1');
```

## Laravel Boost

If you use Laravel Boost, run `php artisan boost:install`. Boost reads the package's guideline from `resources/boost/guidelines` and, on Boost versions that support package skills, the `kakaw-development` skill from `resources/boost/skills`.

## What v1 does not do

- Partial credit notes
- Document types other than invoices (`FACT`)
- PDF rendering
- XML parsing
- Database tables, routes, views or a settings screen
- A testing fake
- Idempotent retries
- Annulment deadline rules

## Adding a certifier

See [docs/adding-a-certifier.md](docs/adding-a-certifier.md). Contributor rules are in [docs/rules.md](docs/rules.md).

## License

MIT.

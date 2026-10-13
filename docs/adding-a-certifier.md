# Adding a certifier

A certifier is a driver: a class behind the `Kakaw\Contracts\Certifier` contract, resolved by `Kakaw\KakawManager` (an `Illuminate\Support\Manager`). Read [rules.md](rules.md) first.

## Collect first

Before writing code, read the certifier's documentation and write down:

- Authentication scheme and token lifetime.
- Endpoints per environment (test and production).
- Request format for certifying a document.
- How success and errors are signalled (HTTP status, a code field, both).
- What annulment needs as input.
- Whether a total-credit-note shortcut exists, and what it needs.
- How to look up a taxpayer by NIT, and a person by CUI.
- How a receiver identified by CUI is marked in a document.
- How to fetch a certified document again.

Where the documentation and observed behaviour disagree, record both in the driver's docs page, as `docs/digifact.md` does.

## Steps

Use a certifier called `Acme` as the example.

1. Create `src/Certifiers/Acme/`. Nothing certifier-specific goes outside it.
2. Add a config value object (`AcmeConfig`) with `fromArray(array $config)`. Throw `InvalidConfigurationException::missing()` for absent required keys and `::invalid()` for bad values.
3. Add a client (`AcmeClient`) that wraps `Illuminate\Http\Client\Factory`. Handle login, token caching and a single retry on an expired token, and throw `AuthenticationException`, `CertifierException::unreachable()` and `CertifierException::unreadable()` for transport problems. Tokens never leave the client.
4. Add a payload builder (like `NucDocument`) that turns a `Document` into the certifier's request. Format amounts with `Kakaw\Support\Amount::format()`.
5. Add the certifier class implementing `Kakaw\Contracts\Certifier`: `verifyCredentials()`, `certify()`, `annul()`, `taxpayer()`, `taxpayerByCui()`, `document()` and `status()`. Throw `CertifierException::rejected()` carrying the certifier's own message when it refuses a request. Return `null` from `taxpayer()` and `document()` when nothing is found.
6. Implement `Kakaw\Contracts\IssuesTotalCreditNotes` on the same class only if the certifier can do it. Further optional abilities are further interfaces, never new methods on `Certifier`.
7. Register the driver on `KakawManager` by adding a method named after the driver, `create{Studly}Certifier(array $config): Certifier`, so `acme` resolves through `createAcmeCertifier()`. Build the config object, the client and the certifier there, as `createDigifactCertifier()` does.
8. Add a config block under `kakaw.certifiers` in `config/kakaw.php` with a `driver` key and `KAKAW_ACME_*` environment keys:

```php
'acme' => [
    'driver' => 'acme',
    'username' => env('KAKAW_ACME_USERNAME'),
    'password' => env('KAKAW_ACME_PASSWORD'),
],
```

9. Add tests mirroring `tests/Feature/Digifact/`: config, client (login, caching, retry on 401, unreachable), payload, and the certifier operations, all with `Http::fake`. Add fixtures under `tests/Fixtures/`.
10. Add a docs page `docs/acme.md` with endpoints, credentials, field mapping and anything unverified, and mention the driver in the README.

## From an application

An application can register a certifier without editing the package. Configure a block with a `driver` key, then extend the manager:

```php
use Kakaw\Facades\Kakaw;

config()->set('kakaw.certifiers.acme', ['driver' => 'acme', 'username' => '...']);

Kakaw::extend('acme', fn ($app, array $config) => new AcmeCertifier($config));
```

The closure receives the container and the block's config, and must return a `Certifier`. The same closure serves `Kakaw::build(['driver' => 'acme', ...])`.

## Kakaw

Kakaw issues FEL electronic invoices for Guatemala (SAT) through pluggable certifiers. v1 ships the Digifact driver.

- Supports invoices (`FACT`) and total credit notes only. No partial credit notes, PDF rendering, XML parsing or tables.
- Amounts are strings, integers or `BigDecimal`, never floats. Prices include IVA.
- A `Document` to receiver `CF` in GTQ must total less than Q2,500; building it throws `InvalidDocumentException`.
- Tokens are handled internally. Do not store or pass them.
- Call everything through the `Kakaw\Facades\Kakaw` facade.

@verbatim
<code-snippet name="Certify an invoice" lang="php">
use Kakaw\Data\Document;
use Kakaw\Data\Item;
use Kakaw\Data\Receiver;
use Kakaw\Facades\Kakaw;

$certified = Kakaw::certify(new Document(
    issuer: Kakaw::issuer(),
    receiver: Receiver::finalConsumer(),
    items: collect([new Item(description: 'Chocolate', quantity: 2, unitPrice: '25.50')]),
));

$certified->authorization;
</code-snippet>

<code-snippet name="Other operations" lang="php">
Kakaw::annul(new Annulment(authorization: $a, issuerTaxId: $nit, receiverTaxId: 'CF', issuedAt: $issuedAt, reason: $reason));
Kakaw::creditNote(new TotalCreditNote(authorization: $a, issuerTaxId: $nit, reason: $reason));
Kakaw::taxpayer('1234567-8');
Kakaw::document($authorization);
Kakaw::build($credentials)->verifyCredentials();
</code-snippet>
@endverbatim

Exceptions, all extending `Kakaw\Exceptions\KakawException`: `InvalidDocumentException`, `InvalidConfigurationException`, `AuthenticationException`, `CertifierException` (the certifier refused or was unreachable), `UnsupportedOperationException`.

In tests, use `Http::fake` and `Http::preventStrayRequests()`; never reach a real certifier.

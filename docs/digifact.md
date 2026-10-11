# Digifact

Driver `digifact`, in `src/Certifiers/Digifact/`.

## Status

Nothing has been verified against Digifact live. Every test uses `Http::fake`. The request shapes come from Digifact's portal documentation and from production behaviour observed in an existing integration. Work through the checklist below the first time working credentials are available.

## Credentials

| Key | Meaning |
|---|---|
| `tax_id` | The issuer's NIT. Hyphens are removed and it is left-padded with zeros to 12 digits where Digifact needs it. |
| `username` | The Digifact user name. |
| `password` | The Digifact password. |
| `environment` | `test` (default) or `production`. |
| `base_url` | Optional override of the environment's base URL. |

The login username is `GT.{NIT padded to 12}.{username}`.

## Endpoints

| Environment | Base URL |
|---|---|
| test | `https://testnucgt.digifact.com/api/` |
| production | `https://nucgt.digifact.com/gt.com.apinuc/api/` |

The `Authorization` header carries the raw token, with no `Bearer` prefix.

| Operation | Method and path | Request |
|---|---|---|
| Login | `POST login/get_token` | Body `Username` (full login username), `Password`. |
| Certify | `POST v2/transform/nuc_json` | Query `TAXID` (padded), `USERNAME` (plain), `FORMAT=XML`. Body is the NUC JSON document. |
| Annul | `POST CancelFelGT` | Body `TaxId`, `Autorizacion`, `IdReceptor`, `FechaEmisionDocumentoAnular` (`Y-m-d\TH:i:s`), `MotivoAnulacion`, `Username`. |
| NIT lookup | `GET Shared` | Query `Country=GT`, `Username` (full login username), `TaxID` (padded), `Data1=SHARED_GETINFONITcom`, `Data2=NIT\|{nit}`. |
| Fetch document | `GET GetDocument` | Query `AUTHNUMBER`, `Username` (full login username), `TaxID` (padded), `FORMAT=XML`. |
| Total credit note | `POST cert/ncredtotal` | Body `Staxid`, `Authnumber`, `FechaEmision` (`Y-m-d H:i:s`), `MotivoAjuste`, `Formatos=xml`, `Username`, optional `ReferenciaInterna`. |

Only XML is requested. Rendering a PDF is the application's job.

## Response mapping

Response keys are read case-insensitively, because the portal and production disagree on casing (`token` and `Token`, `Numero` and `NUMERO`).

Login: `Token` and `expira_en` (format `n/j/Y g:i:s A`, Guatemala time). With no expiry the token is cached for one hour; otherwise it is cached until five minutes before expiry. A rejected login is an HTTP 200 whose body has no token and a `response` message such as `Credenciales incorrectas`; the driver throws `AuthenticationException` with that message. A 401 on any other call clears the token, logs in once and retries once.

Certify and total credit note: success is `code` equal to `1`; anything else throws `CertifierException` with Digifact's message.

| `CertifiedDocument` | Digifact field |
|---|---|
| `authorization` | `authNumber` |
| `series` | `batch` |
| `number` | `serial` |
| `issuedAt` | `issuedTimeStamp` (falls back to the date sent) |
| `certifiedAt` | `enrolledTimeStamp` |
| `issuerTaxId` | `taxID` (falls back to the configured NIT) |
| `issuerName` | `name` |
| `receiverTaxId` | `receiverTaxID` |
| `receiverName` | `receiverName` |
| `total` | `totalAmount` |
| `xml` | `responseData1`, base64-decoded |

A response without `responseData1` or without `authNumber` is treated as a rejection.

Annul: success is `Codigo` equal to `1`. `AnnulledDocument` maps `Autorizacion`, `Serie`, `Numero` and `ResponseDATA1` (base64 XML).

NIT lookup: `RESPONSE[0]` with `NIT` and `NOMBRE`; an empty or missing `RESPONSE` returns `null`.

Fetch document: `RESPONSE[0].ResponseData1`, base64 XML; missing returns `null`.

## NUC JSON

The document is sent in Digifact's NUC JSON format: `Version` (`1.00`), `CountryCode` (`GT`), `Header`, `Seller`, `Buyer`, `Items`, `Totals` and `AdditionalDocumentInfo`. The names `AdditionlInfo`, `AditionalInfo` and `AditionalData` are Digifact's own spellings and are sent literally. Amounts are strings with six decimals. Every document carries one ADENDA block with a fresh GUID and `VALIDAR_REFERENCIA_INTERNA = NO_VALIDAR`.

## Where the portal and production differ

| Area | Portal | Production behaviour observed | Driver does |
|---|---|---|---|
| Login response | `token` | `Token`, `expira_en` | Reads either. |
| Login rejection | Not documented | HTTP 200 with `response` message | Throws `AuthenticationException`. |
| Annul NIT key | `Taxid` | `TaxId` | `TaxId`. |
| Annul `Username` | The Digifact user | A value supplied by the caller | The Digifact user from the credentials. |
| `Shared` and `GetDocument` parameters | Upper case, plain `USERNAME` | Mixed case, full `GT.{nit}.{user}` | Follows production. |
| Certify failure | Code `1` is success | Only `3000` treated as failure | Anything but `1` fails. |
| `AdditionalDocumentInfo` | Rejected without an ADENDA | Sent as an empty list | Always sends one ADENDA. |
| Amount precision | Six decimals, strings | Five decimals, floats | Six decimals, strings. |
| NIT lookup miss | `RESPONSE: []` | Falsy `RESPONSE` | Either returns `null`. |

Digifact's own FACT example is arithmetically inconsistent: one line shows a taxable amount that does not match its total, and one line truncates IVA while the document total rounds. There is no reference to copy for the IVA split, so the driver computes taxable as total divided by 1.12 rounded half-up, and IVA as total minus taxable.

## Unverified on a live call

Tick each off once Digifact credentials work.

- [ ] Login returns `Token` and `expira_en` in the expected format.
- [ ] Certify accepts the ADENDA block as sent.
- [ ] Certify accepts the six-decimal amounts and the `total - taxable` IVA split.
- [ ] Certify accepts a receiver with no `AddressInfo`.
- [ ] Annul accepts the Digifact username in `Username`.
- [ ] `cert/ncredtotal` accepts the body and returns the certify-shaped response.
- [ ] `cert/ncredtotal`: `FechaEmision` means the credit note's date (what the driver sends) or the original document's date; the portal does not say.
- [ ] Which identity fields the credit-note response actually returns; the portal lists fewer than certify does.
- [ ] NIT lookup returns `NIT` and `NOMBRE` under `RESPONSE[0]`.

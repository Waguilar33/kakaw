# Rules

Rules a contributor may not break.

1. Nothing certifier-specific lives outside `src/Certifiers/{Name}/`. The core, the contracts, the data classes and the manager must read the same whichever certifier is configured.
2. The `Certifier` contract and the result objects change only with a major version. Applications depend on them directly, and a silent change would break invoicing.
3. Inputs and results are neutral, and SAT vocabulary is written in English. Callers hand in the same `Document` and get back the same `CertifiedDocument` for every certifier; drivers translate, and nothing certifier-shaped crosses the contract.
4. Optional abilities are separate capability interfaces, such as `IssuesTotalCreditNotes`. A driver without the ability makes the manager throw `UnsupportedOperationException`, which is clearer than a silent mismatch between what a certifier can take and what the contract promises.
5. Amounts are `BigDecimal`, with six decimals on the wire, never floats. Float arithmetic on money drifts, and the certifier validates the totals.
6. A SAT rule needs a cited source before it enters the core. A wrong legal rule in a public package is worse than none; rules without a citation stay in the application.
7. Tokens are never returned, logged or stored outside the cache. Callers never see a token, and the cache key includes environment, NIT and username so one company never receives another's token.
8. Laravel helpers over raw PHP: the HTTP client, the cache, collections, `Str`, `Arr` and `CarbonImmutable`. The package should behave like the framework it extends.
9. Every driver is tested with `Http::fake`, and stray requests are forbidden. Tests must never reach a real certifier, and a request nobody faked is a bug.
10. The package owns no tables, routes or views. Applications keep their own settings screens and storage; credentials arrive from config or at call time through `Kakaw::build()`.

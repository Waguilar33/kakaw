# Kakaw: notes for agents working on the package

- Read `docs/rules.md` first. Those rules are not negotiable.
- To add a certifier, follow `docs/adding-a-certifier.md`.
- Digifact wire facts and the unverified-live checklist are in `docs/digifact.md`.
- Tests: `vendor/bin/pest`
- Static analysis: `vendor/bin/phpstan analyse`
- Formatting: `vendor/bin/pint`
- Style: no code comments. Laravel helpers before raw PHP.
- Every test fakes HTTP with `Http::fake`; stray requests are forbidden.

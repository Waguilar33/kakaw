# Changelog

## Unreleased

- Certify invoices (`FACT`) through `Kakaw::certify()`.
- Annul certified documents through `Kakaw::annul()`.
- Issue total credit notes through `Kakaw::creditNote()`.
- Look up a taxpayer by NIT with `Kakaw::taxpayer()`.
- Fetch a certified document with `Kakaw::document()`.
- Verify credentials with `Kakaw::verifyCredentials()`.
- Build a certifier from runtime credentials with `Kakaw::build()`.
- Register custom certifiers with `Kakaw::extend()`.
- Digifact driver with automatic token caching and refresh.
- Final consumer limit (Q2,500) enforced when a `Document` is built.
- Laravel Boost guideline and `kakaw-development` skill.

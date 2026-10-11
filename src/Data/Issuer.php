<?php

namespace Kakaw\Data;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Kakaw\Enums\VatAffiliation;
use Kakaw\Exceptions\InvalidDocumentException;
use Kakaw\Support\Nit;

final readonly class Issuer
{
    public string $taxId;

    /** @var Collection<int, Phrase> */
    public Collection $phrases;

    /**
     * @param  Collection<int, Phrase>|null  $phrases
     */
    public function __construct(
        string $taxId,
        public string $name,
        public string $commercialName,
        public Address $address,
        public string $establishmentCode = '1',
        public VatAffiliation $vatAffiliation = VatAffiliation::General,
        ?Collection $phrases = null,
    ) {
        $this->taxId = Nit::normalize($taxId);
        $this->phrases = $phrases ?? collect([new Phrase(1, 1)]);
    }

    /**
     * @param  array<string, mixed>  $issuer
     */
    public static function fromArray(array $issuer): self
    {
        $required = collect([
            'tax_id',
            'name',
            'commercial_name',
            'address.street',
            'address.postal_code',
            'address.municipality',
            'address.department',
        ]);

        $missing = $required->first(fn (string $key): bool => blank(Arr::get($issuer, $key)));

        if ($missing !== null) {
            throw InvalidDocumentException::because("Issuer is missing the required value [{$missing}].");
        }

        /** @var array<int, array{type: int|string, scenario: int|string}>|null $phraseConfigs */
        $phraseConfigs = Arr::get($issuer, 'phrases');

        $phrases = $phraseConfigs === null
            ? null
            : Collection::make($phraseConfigs)
                ->map(fn (array $phrase): Phrase => new Phrase((int) $phrase['type'], (int) $phrase['scenario']))
                ->values();

        return new self(
            taxId: (string) Arr::get($issuer, 'tax_id'),
            name: (string) Arr::get($issuer, 'name'),
            commercialName: (string) Arr::get($issuer, 'commercial_name'),
            address: new Address(
                street: (string) Arr::get($issuer, 'address.street'),
                postalCode: (string) Arr::get($issuer, 'address.postal_code'),
                municipality: (string) Arr::get($issuer, 'address.municipality'),
                department: (string) Arr::get($issuer, 'address.department'),
                country: (string) Arr::get($issuer, 'address.country', 'GT'),
            ),
            establishmentCode: (string) Arr::get($issuer, 'establishment_code', '1'),
            vatAffiliation: VatAffiliation::from((string) Arr::get($issuer, 'vat_affiliation', VatAffiliation::General->value)),
            phrases: $phrases,
        );
    }
}

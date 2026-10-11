<?php

namespace Kakaw\Data;

use Carbon\CarbonImmutable;
use Kakaw\Support\Nit;

final readonly class TotalCreditNote
{
    public string $issuerTaxId;

    public CarbonImmutable $issuedAt;

    public function __construct(
        public string $authorization,
        string $issuerTaxId,
        public string $reason,
        ?CarbonImmutable $issuedAt = null,
        public ?string $reference = null,
    ) {
        $this->issuerTaxId = Nit::normalize($issuerTaxId);
        $this->issuedAt = ($issuedAt ?? CarbonImmutable::now())->setTimezone(Document::TIMEZONE);
    }
}

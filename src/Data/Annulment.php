<?php

namespace Kakaw\Data;

use Carbon\CarbonImmutable;
use Kakaw\Support\Nit;

final readonly class Annulment
{
    public string $issuerTaxId;

    public string $receiverTaxId;

    public CarbonImmutable $issuedAt;

    public function __construct(
        public string $authorization,
        string $issuerTaxId,
        string $receiverTaxId,
        CarbonImmutable $issuedAt,
        public string $reason,
    ) {
        $this->issuerTaxId = Nit::normalize($issuerTaxId);
        $this->receiverTaxId = Nit::normalize($receiverTaxId);
        $this->issuedAt = $issuedAt->setTimezone(Document::TIMEZONE);
    }
}

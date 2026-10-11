<?php

namespace Kakaw\Data\Results;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

final readonly class CertifiedDocument
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $authorization,
        public string $series,
        public string $number,
        public CarbonImmutable $issuedAt,
        public ?CarbonImmutable $certifiedAt,
        public string $issuerTaxId,
        public ?string $issuerName,
        public ?string $receiverTaxId,
        public ?string $receiverName,
        public ?BigDecimal $total,
        public string $xml,
        public array $raw,
    ) {}
}

<?php

namespace Kakaw\Data\Results;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

final readonly class DocumentStatus
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $authorization,
        public ?string $type,
        public ?string $series,
        public ?string $number,
        public ?string $status,
        public ?CarbonImmutable $issuedAt,
        public ?CarbonImmutable $certifiedAt,
        public ?string $issuerTaxId,
        public ?string $receiverTaxId,
        public ?string $receiverName,
        public ?BigDecimal $total,
        public ?string $xml,
        public ?string $taxAuthorityReceipt,
        public ?string $annulmentReceipt,
        public ?string $reference,
        public array $raw,
    ) {}
}

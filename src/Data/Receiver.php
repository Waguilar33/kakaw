<?php

namespace Kakaw\Data;

use Kakaw\Support\Nit;

final readonly class Receiver
{
    public string $taxId;

    public function __construct(
        string $taxId,
        public string $name,
        public ?Address $address = null,
    ) {
        $this->taxId = Nit::normalize($taxId);
    }

    public static function finalConsumer(string $name = 'Consumidor Final'): self
    {
        return new self(Nit::FINAL_CONSUMER, $name);
    }

    public function isFinalConsumer(): bool
    {
        return Nit::isFinalConsumer($this->taxId);
    }
}

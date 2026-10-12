<?php

namespace Kakaw\Data;

use Kakaw\Enums\IdentificationType;
use Kakaw\Exceptions\InvalidDocumentException;
use Kakaw\Support\Cui;
use Kakaw\Support\Nit;

final readonly class Receiver
{
    public string $taxId;

    public function __construct(
        string $taxId,
        public string $name,
        public ?Address $address = null,
        public IdentificationType $identificationType = IdentificationType::Nit,
    ) {
        if ($identificationType === IdentificationType::Cui && ! Cui::isValid($taxId)) {
            throw InvalidDocumentException::because('A CUI must have exactly 13 digits.');
        }

        $this->taxId = $identificationType === IdentificationType::Cui
            ? Cui::normalize($taxId)
            : Nit::normalize($taxId);
    }

    public static function finalConsumer(string $name = 'Consumidor Final'): self
    {
        return new self(Nit::FINAL_CONSUMER, $name);
    }

    public static function withCui(string $cui, string $name, ?Address $address = null): self
    {
        return new self($cui, $name, $address, IdentificationType::Cui);
    }

    public function isFinalConsumer(): bool
    {
        return $this->identificationType === IdentificationType::Nit && Nit::isFinalConsumer($this->taxId);
    }
}

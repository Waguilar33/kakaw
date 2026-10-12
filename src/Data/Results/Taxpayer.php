<?php

namespace Kakaw\Data\Results;

use Kakaw\Enums\IdentificationType;

final readonly class Taxpayer
{
    public function __construct(
        public string $taxId,
        public string $name,
        public IdentificationType $identificationType = IdentificationType::Nit,
    ) {}
}

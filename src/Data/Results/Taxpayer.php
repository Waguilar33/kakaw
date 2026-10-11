<?php

namespace Kakaw\Data\Results;

final readonly class Taxpayer
{
    public function __construct(
        public string $taxId,
        public string $name,
    ) {}
}

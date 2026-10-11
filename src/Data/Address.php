<?php

namespace Kakaw\Data;

final readonly class Address
{
    public function __construct(
        public string $street,
        public string $postalCode,
        public string $municipality,
        public string $department,
        public string $country = 'GT',
    ) {}
}

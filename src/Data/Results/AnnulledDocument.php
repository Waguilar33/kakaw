<?php

namespace Kakaw\Data\Results;

final readonly class AnnulledDocument
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $authorization,
        public ?string $series,
        public ?string $number,
        public ?string $xml,
        public array $raw,
    ) {}
}

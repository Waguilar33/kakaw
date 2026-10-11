<?php

namespace Kakaw\Data\Results;

final readonly class RetrievedDocument
{
    public function __construct(
        public string $authorization,
        public string $xml,
    ) {}
}

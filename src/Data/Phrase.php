<?php

namespace Kakaw\Data;

final readonly class Phrase
{
    public function __construct(
        public int $type,
        public int $scenario,
    ) {}
}

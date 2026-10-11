<?php

namespace Kakaw\Contracts;

use Kakaw\Data\Annulment;
use Kakaw\Data\Document;
use Kakaw\Data\Results\AnnulledDocument;
use Kakaw\Data\Results\CertifiedDocument;
use Kakaw\Data\Results\RetrievedDocument;
use Kakaw\Data\Results\Taxpayer;

interface Certifier
{
    public function verifyCredentials(): void;

    public function certify(Document $document): CertifiedDocument;

    public function annul(Annulment $annulment): AnnulledDocument;

    public function taxpayer(string $taxId): ?Taxpayer;

    public function document(string $authorization): ?RetrievedDocument;
}

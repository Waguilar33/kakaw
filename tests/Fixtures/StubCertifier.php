<?php

namespace Kakaw\Tests\Fixtures;

use Carbon\CarbonImmutable;
use Kakaw\Contracts\Certifier;
use Kakaw\Data\Annulment;
use Kakaw\Data\Document;
use Kakaw\Data\Results\AnnulledDocument;
use Kakaw\Data\Results\CertifiedDocument;
use Kakaw\Data\Results\RetrievedDocument;
use Kakaw\Data\Results\Taxpayer;
use Kakaw\Enums\IdentificationType;

class StubCertifier implements Certifier
{
    /** @var array<int, string> */
    public array $calls = [];

    public function verifyCredentials(): void
    {
        $this->calls[] = 'verifyCredentials';
    }

    public function certify(Document $document): CertifiedDocument
    {
        $this->calls[] = 'certify';

        return new CertifiedDocument(
            authorization: 'AUTH-1',
            series: 'A',
            number: '1',
            issuedAt: CarbonImmutable::now(),
            certifiedAt: null,
            issuerTaxId: '1234567',
            issuerName: null,
            receiverTaxId: null,
            receiverName: null,
            total: null,
            xml: '<xml/>',
            raw: [],
        );
    }

    public function annul(Annulment $annulment): AnnulledDocument
    {
        $this->calls[] = 'annul';

        return new AnnulledDocument('AUTH-1', null, null, null, []);
    }

    public function taxpayer(string $taxId): ?Taxpayer
    {
        $this->calls[] = 'taxpayer';

        return new Taxpayer($taxId, 'Stub Taxpayer');
    }

    public function taxpayerByCui(string $cui): ?Taxpayer
    {
        $this->calls[] = 'taxpayerByCui';

        return new Taxpayer($cui, 'Stub Taxpayer', IdentificationType::Cui);
    }

    public function document(string $authorization): ?RetrievedDocument
    {
        $this->calls[] = 'document';

        return new RetrievedDocument($authorization, '<xml/>');
    }
}

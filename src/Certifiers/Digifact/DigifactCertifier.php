<?php

namespace Kakaw\Certifiers\Digifact;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kakaw\Contracts\Certifier;
use Kakaw\Contracts\IssuesTotalCreditNotes;
use Kakaw\Data\Annulment;
use Kakaw\Data\Document;
use Kakaw\Data\Results\AnnulledDocument;
use Kakaw\Data\Results\CertifiedDocument;
use Kakaw\Data\Results\RetrievedDocument;
use Kakaw\Data\Results\Taxpayer;
use Kakaw\Data\TotalCreditNote;
use Kakaw\Exceptions\CertifierException;
use Kakaw\Support\Nit;

final class DigifactCertifier implements Certifier, IssuesTotalCreditNotes
{
    private const CERTIFIER = 'digifact';

    public function __construct(
        private readonly DigifactClient $client,
        private readonly DigifactConfig $config,
    ) {}

    public function verifyCredentials(): void
    {
        $this->client->login();
    }

    public function certify(Document $document): CertifiedDocument
    {
        $data = $this->client->post('v2/transform/nuc_json', NucDocument::from($document), [
            'TAXID' => $this->config->paddedTaxId(),
            'USERNAME' => $this->config->username,
            'FORMAT' => 'XML',
        ]);

        return $this->certified(new DigifactResponse($data), $document->issuedAt);
    }

    public function annul(Annulment $annulment): AnnulledDocument
    {
        $data = $this->client->post('CancelFelGT', [
            'TaxId' => $annulment->issuerTaxId,
            'Autorizacion' => $annulment->authorization,
            'IdReceptor' => $annulment->receiverTaxId,
            'FechaEmisionDocumentoAnular' => $annulment->issuedAt->format('Y-m-d\TH:i:s'),
            'MotivoAnulacion' => $annulment->reason,
            'Username' => $this->config->username,
        ]);

        $response = new DigifactResponse($data);

        if (! $response->succeeded('Codigo')) {
            throw CertifierException::rejected(
                self::CERTIFIER,
                $response->string('Codigo') ?? '',
                $response->string('Mensaje') ?? '',
                $data,
            );
        }

        return new AnnulledDocument(
            authorization: $response->string('Autorizacion') ?? $annulment->authorization,
            series: $response->string('Serie'),
            number: $response->string('Numero'),
            xml: $response->decoded('ResponseDATA1'),
            raw: $data,
        );
    }

    public function taxpayer(string $taxId): ?Taxpayer
    {
        $data = $this->client->get('Shared', [
            'Country' => 'GT',
            'Username' => $this->config->loginUsername(),
            'TaxID' => $this->config->paddedTaxId(),
            'Data1' => 'SHARED_GETINFONITcom',
            'Data2' => 'NIT|'.Nit::normalize($taxId),
        ]);

        $match = (new DigifactResponse($data))->first('RESPONSE');
        $nit = $match?->string('NIT');
        $name = $match?->string('NOMBRE');

        return $nit === null || $name === null ? null : new Taxpayer($nit, $name);
    }

    public function document(string $authorization): ?RetrievedDocument
    {
        $data = $this->client->get('GetDocument', [
            'AUTHNUMBER' => $authorization,
            'Username' => $this->config->loginUsername(),
            'TaxID' => $this->config->paddedTaxId(),
            'FORMAT' => 'XML',
        ]);

        $xml = (new DigifactResponse($data))->first('RESPONSE')?->decoded('ResponseData1');

        return $xml === null ? null : new RetrievedDocument($authorization, $xml);
    }

    public function creditNote(TotalCreditNote $creditNote): CertifiedDocument
    {
        $body = [
            'Staxid' => $creditNote->issuerTaxId,
            'Authnumber' => $creditNote->authorization,
            'FechaEmision' => $creditNote->issuedAt->format('Y-m-d H:i:s'),
            'MotivoAjuste' => $creditNote->reason,
            'Formatos' => 'xml',
            'Username' => $this->config->username,
        ];

        if ($creditNote->reference !== null) {
            $body['ReferenciaInterna'] = $creditNote->reference;
        }

        return $this->certified(new DigifactResponse($this->client->post('cert/ncredtotal', $body)), $creditNote->issuedAt);
    }

    private function certified(DigifactResponse $response, CarbonImmutable $sentAt): CertifiedDocument
    {
        if (! $response->succeeded('code')) {
            throw CertifierException::rejected(
                self::CERTIFIER,
                $response->string('code') ?? '',
                Collection::make([$response->string('message'), $response->string('description')])->filter()->implode(': '),
                $response->all(),
            );
        }

        $xml = $response->decoded('responseData1');

        if ($xml === null) {
            throw CertifierException::rejected(
                self::CERTIFIER,
                $response->string('code') ?? '',
                'The certifier returned no document XML.',
                $response->all(),
            );
        }

        $authorization = $response->string('authNumber');

        if (blank($authorization)) {
            throw CertifierException::rejected(
                self::CERTIFIER,
                $response->string('code') ?? '',
                'The certifier returned no authorization number.',
                $response->all(),
            );
        }

        $total = $response->string('totalAmount');

        return new CertifiedDocument(
            authorization: $authorization,
            series: $response->string('batch') ?? '',
            number: $response->string('serial') ?? '',
            issuedAt: $this->guatemalaTime($response->string('issuedTimeStamp')) ?? $sentAt,
            certifiedAt: $this->guatemalaTime($response->string('enrolledTimeStamp')),
            issuerTaxId: Nit::normalize(Str::ltrim($response->string('taxID') ?? $this->config->taxId, '0')),
            issuerName: $response->string('name'),
            receiverTaxId: $response->string('receiverTaxID'),
            receiverName: $response->string('receiverName'),
            total: $total === null ? null : BigDecimal::of($total),
            xml: $xml,
            raw: $response->all(),
        );
    }

    private function guatemalaTime(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value, Document::TIMEZONE);
    }
}

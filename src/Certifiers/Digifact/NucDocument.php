<?php

namespace Kakaw\Certifiers\Digifact;

use Brick\Math\BigDecimal;
use Illuminate\Support\Str;
use Kakaw\Data\Address;
use Kakaw\Data\Document;
use Kakaw\Data\Item;
use Kakaw\Data\Phrase;
use Kakaw\Enums\IdentificationType;
use Kakaw\Enums\ItemType;
use Kakaw\Support\Amount;

final class NucDocument
{
    private const VERSION = '1.00';

    private const COUNTRY_CODE = 'GT';

    private const VAT_CODE = '1';

    private const VAT_NAME = 'IVA';

    /**
     * @return array<string, mixed>
     */
    public static function from(Document $document): array
    {
        return [
            'Version' => self::VERSION,
            'CountryCode' => self::COUNTRY_CODE,
            'Header' => [
                'DocType' => $document->type->value,
                'IssuedDateTime' => $document->issuedAt->format('Y-m-d\TH:i:sP'),
                'Currency' => $document->currency,
            ],
            'Seller' => self::seller($document),
            'Buyer' => self::buyer($document),
            'Items' => $document->items
                ->values()
                ->map(fn (Item $item, int $index): array => self::item($item, $index + 1))
                ->all(),
            'Totals' => [
                'TotalTaxes' => [
                    'TotalTax' => [
                        ['Description' => self::VAT_NAME, 'Amount' => Amount::format($document->vat())],
                    ],
                ],
                'GrandTotal' => ['InvoiceTotal' => Amount::format($document->total())],
            ],
            'AdditionalDocumentInfo' => [
                'AdditionalInfo' => [
                    [
                        'Code' => Str::upper((string) Str::uuid()),
                        'Type' => 'ADENDA',
                        'AditionalInfo' => [
                            ['Name' => 'VALIDAR_REFERENCIA_INTERNA', 'Data' => null, 'Value' => 'NO_VALIDAR'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function seller(Document $document): array
    {
        $issuer = $document->issuer;

        return [
            'TaxID' => $issuer->taxId,
            'TaxIDAdditionalInfo' => [
                ['Name' => 'AfiliacionIVA', 'Data' => null, 'Value' => $issuer->vatAffiliation->value],
            ],
            'Name' => $issuer->name,
            'AdditionlInfo' => $issuer->phrases
                ->values()
                ->flatMap(fn (Phrase $phrase, int $index): array => [
                    ['Name' => 'TipoFrase', 'Data' => (string) ($index + 1), 'Value' => (string) $phrase->type],
                    ['Name' => 'Escenario', 'Data' => (string) ($index + 1), 'Value' => (string) $phrase->scenario],
                ])
                ->all(),
            'BranchInfo' => [
                'Code' => $issuer->establishmentCode,
                'Name' => $issuer->commercialName,
                'AddressInfo' => self::address($issuer->address),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buyer(Document $document): array
    {
        $receiver = $document->receiver;

        return [
            'TaxID' => $receiver->taxId,
            ...($receiver->identificationType === IdentificationType::Cui ? ['TaxIDType' => IdentificationType::Cui->value] : []),
            'Name' => $receiver->name,
            ...($receiver->address === null ? [] : ['AddressInfo' => self::address($receiver->address)]),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function address(Address $address): array
    {
        return [
            'Address' => $address->street,
            'City' => $address->postalCode,
            'District' => $address->municipality,
            'State' => $address->department,
            'Country' => $address->country,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(Item $item, int $number): array
    {
        return [
            'Number' => (string) $number,
            'Codes' => null,
            'Type' => $item->type === ItemType::Service ? 'Servicio' : 'Bien',
            'Description' => $item->description,
            'Qty' => Amount::format($item->quantity),
            'UnitOfMeasure' => $item->unitOfMeasure,
            'Price' => Amount::format($item->unitPrice),
            'Discounts' => self::discounts($item->discount),
            'Taxes' => [
                'Tax' => [
                    [
                        'Code' => self::VAT_CODE,
                        'Description' => self::VAT_NAME,
                        'TaxableAmount' => Amount::format($item->taxableAmount()),
                        'Amount' => Amount::format($item->vat()),
                    ],
                ],
            ],
            'Totals' => ['TotalItem' => Amount::format($item->total())],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function discounts(BigDecimal $discount): ?array
    {
        if ($discount->isZero()) {
            return null;
        }

        return ['Discount' => [['Amount' => Amount::format($discount)]]];
    }
}

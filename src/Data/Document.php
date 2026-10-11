<?php

namespace Kakaw\Data;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Kakaw\Enums\DocumentType;
use Kakaw\Exceptions\InvalidDocumentException;
use Kakaw\Support\Amount;

final readonly class Document
{
    public const string TIMEZONE = 'America/Guatemala';

    public const string FINAL_CONSUMER_LIMIT = '2500';

    public CarbonImmutable $issuedAt;

    /**
     * @param  Collection<int, Item>  $items
     */
    public function __construct(
        public Issuer $issuer,
        public Receiver $receiver,
        public Collection $items,
        ?CarbonImmutable $issuedAt = null,
        public DocumentType $type = DocumentType::Invoice,
        public string $currency = 'GTQ',
    ) {
        $this->issuedAt = ($issuedAt ?? CarbonImmutable::now())->setTimezone(self::TIMEZONE);

        if ($items->isEmpty()) {
            throw InvalidDocumentException::because('A document needs at least one item.');
        }

        if (! self::containsOnlyItems($items)) {
            throw InvalidDocumentException::because('Every document item must be an Item instance.');
        }

        if (
            $receiver->isFinalConsumer()
            && $currency === 'GTQ'
            && $this->total()->isGreaterThanOrEqualTo(self::FINAL_CONSUMER_LIMIT)
        ) {
            throw InvalidDocumentException::because('Documents to a final consumer must total less than Q2,500.00.');
        }
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    private static function containsOnlyItems(Collection $items): bool
    {
        return $items->every(fn (mixed $item): bool => $item instanceof Item);
    }

    public function total(): BigDecimal
    {
        return Amount::round($this->sum(fn (Item $item): BigDecimal => $item->total()));
    }

    public function vat(): BigDecimal
    {
        return Amount::round($this->sum(fn (Item $item): BigDecimal => $item->vat()));
    }

    /**
     * @param  callable(Item): BigDecimal  $amount
     */
    private function sum(callable $amount): BigDecimal
    {
        return $this->items->reduce(
            fn (BigDecimal $carry, Item $item): BigDecimal => $carry->plus($amount($item)),
            BigDecimal::zero(),
        );
    }
}

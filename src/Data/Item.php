<?php

namespace Kakaw\Data;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Kakaw\Enums\ItemType;
use Kakaw\Exceptions\InvalidDocumentException;
use Kakaw\Support\Amount;

final readonly class Item
{
    public BigDecimal $quantity;

    public BigDecimal $unitPrice;

    public BigDecimal $discount;

    public function __construct(
        public string $description,
        BigDecimal|int|string $quantity,
        BigDecimal|int|string $unitPrice,
        public ItemType $type = ItemType::Good,
        public string $unitOfMeasure = 'UNI',
        BigDecimal|int|string $discount = 0,
    ) {
        $this->quantity = Amount::of($quantity);
        $this->unitPrice = Amount::of($unitPrice);
        $this->discount = Amount::of($discount);

        if ($this->quantity->isNegative()) {
            throw InvalidDocumentException::because('Item quantity cannot be negative.');
        }

        if ($this->unitPrice->isNegative()) {
            throw InvalidDocumentException::because('Item unit price cannot be negative.');
        }

        if ($this->discount->isNegative()) {
            throw InvalidDocumentException::because('Item discount cannot be negative.');
        }

        if ($this->discount->isGreaterThan($this->unitPrice->multipliedBy($this->quantity))) {
            throw InvalidDocumentException::because('Item discount cannot exceed the line amount.');
        }
    }

    public function total(): BigDecimal
    {
        return Amount::round($this->unitPrice->multipliedBy($this->quantity)->minus($this->discount));
    }

    public function taxableAmount(): BigDecimal
    {
        return Amount::round($this->total()->dividedBy(Amount::VAT_DIVISOR, 6, RoundingMode::HalfUp));
    }

    public function vat(): BigDecimal
    {
        return Amount::round($this->total()->minus($this->taxableAmount()));
    }
}

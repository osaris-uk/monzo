<?php

namespace Osaris\Monzo\Data\Receipts;

use Illuminate\Contracts\Support\Arrayable;

class ReceiptTax implements Arrayable
{
    protected ?string $taxNumber = null;

    public function __construct(
        protected string $description,
        protected int $amount,
        protected string $currency = 'GBP',
    ) {}

    public static function make(string $description, int $amount, string $currency = 'GBP'): static
    {
        return new static($description, $amount, $currency);
    }

    /** e.g. a VAT registration number. */
    public function taxNumber(string $taxNumber): static
    {
        $this->taxNumber = $taxNumber;

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'tax_number' => $this->taxNumber,
        ], fn ($value) => $value !== null);
    }
}

<?php

namespace Osaris\Monzo\Data\Receipts;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

class ReceiptItem implements Arrayable
{
    protected ?float $quantity = null;

    protected ?string $unit = null;

    protected ?int $tax = null;

    /** @var ReceiptItem[] */
    protected array $subItems = [];

    protected bool $isSubItem = false;

    /**
     * @param  int  $amount  Total for this line in minor units (pennies)
     */
    public function __construct(
        protected string $description,
        protected int $amount,
        protected string $currency = 'GBP',
    ) {}

    public static function make(string $description, int $amount, string $currency = 'GBP'): static
    {
        return new static($description, $amount, $currency);
    }

    public function quantity(float $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    /** e.g. "kg" for weighed goods. */
    public function unit(string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    /** Tax on this line in minor units. */
    public function tax(int $tax): static
    {
        $this->tax = $tax;

        return $this;
    }

    /**
     * Monzo supports one level of sub-items; their amounts should add up to
     * this item's amount.
     */
    public function addSubItem(ReceiptItem $item): static
    {
        if ($this->isSubItem || $item->subItems !== []) {
            throw new InvalidArgumentException('Receipt sub-items can only be nested one level deep.');
        }

        $item->isSubItem = true;
        $this->subItems[] = $item;

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'tax' => $this->tax,
            'sub_items' => $this->subItems
                ? array_map(fn (ReceiptItem $i) => $i->toArray(), $this->subItems)
                : null,
        ], fn ($value) => $value !== null);
    }
}

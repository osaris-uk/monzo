<?php

namespace Osaris\Monzo\Data\Receipts;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Builder for PUT /transaction-receipts.
 *
 *   Receipt::make($transactionId, 'order-1234', total: 1299)
 *       ->addItem(ReceiptItem::make('Coffee', 299)->quantity(1))
 *       ->addItem(ReceiptItem::make('Sandwich', 1000))
 *       ->addTax(ReceiptTax::make('VAT', 217)->taxNumber('GB123456789'))
 *       ->addPayment(ReceiptPayment::card(1299, lastFour: '4242'))
 *       ->merchant(ReceiptMerchant::make('Corner Café')->online(false));
 *
 * external_id is your idempotency key: sending the same one again updates
 * the existing receipt instead of creating another.
 */
class Receipt implements Arrayable, JsonSerializable
{
    /** @var ReceiptItem[] */
    protected array $items = [];

    /** @var ReceiptTax[] */
    protected array $taxes = [];

    /** @var ReceiptPayment[] */
    protected array $payments = [];

    protected ?ReceiptMerchant $merchant = null;

    public function __construct(
        protected string $transactionId,
        protected string $externalId,
        protected int $total,
        protected string $currency = 'GBP',
    ) {
        if ($total <= 0) {
            throw new InvalidArgumentException('Receipt total must be a positive amount in minor units.');
        }
    }

    public static function make(string $transactionId, string $externalId, int $total, string $currency = 'GBP'): static
    {
        return new static($transactionId, $externalId, $total, $currency);
    }

    public function addItem(ReceiptItem $item): static
    {
        $this->items[] = $item;

        return $this;
    }

    public function addTax(ReceiptTax $tax): static
    {
        $this->taxes[] = $tax;

        return $this;
    }

    public function addPayment(ReceiptPayment $payment): static
    {
        $this->payments[] = $payment;

        return $this;
    }

    public function merchant(ReceiptMerchant $merchant): static
    {
        $this->merchant = $merchant;

        return $this;
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function toArray(): array
    {
        if ($this->items === []) {
            throw new InvalidArgumentException('A receipt needs at least one item.');
        }

        return array_filter([
            'transaction_id' => $this->transactionId,
            'external_id' => $this->externalId,
            'total' => $this->total,
            'currency' => $this->currency,
            'items' => array_map(fn (ReceiptItem $i) => $i->toArray(), $this->items),
            'taxes' => $this->taxes ? array_map(fn (ReceiptTax $t) => $t->toArray(), $this->taxes) : null,
            'payments' => $this->payments ? array_map(fn (ReceiptPayment $p) => $p->toArray(), $this->payments) : null,
            'merchant' => $this->merchant?->toArray(),
        ], fn ($value) => $value !== null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

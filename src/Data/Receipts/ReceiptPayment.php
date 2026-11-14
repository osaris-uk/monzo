<?php

namespace Osaris\Monzo\Data\Receipts;

use Illuminate\Contracts\Support\Arrayable;
use Osaris\Monzo\Enums\PaymentType;

class ReceiptPayment implements Arrayable
{
    protected array $extra = [];

    public function __construct(
        protected PaymentType $type,
        protected int $amount,
        protected string $currency = 'GBP',
    ) {}

    public static function card(
        int $amount,
        ?string $lastFour = null,
        string $currency = 'GBP',
        ?string $bin = null,
        ?string $authCode = null,
        ?string $aid = null,
        ?string $mid = null,
        ?string $tid = null,
    ): static {
        return (new static(PaymentType::Card, $amount, $currency))->with([
            'last_four' => $lastFour,
            'bin' => $bin,
            'auth_code' => $authCode,
            'aid' => $aid,
            'mid' => $mid,
            'tid' => $tid,
        ]);
    }

    public static function cash(int $amount, string $currency = 'GBP'): static
    {
        return new static(PaymentType::Cash, $amount, $currency);
    }

    public static function giftCard(int $amount, ?string $giftCardType = null, string $currency = 'GBP'): static
    {
        return (new static(PaymentType::GiftCard, $amount, $currency))->with([
            'gift_card_type' => $giftCardType,
        ]);
    }

    /** Add any other payment fields. Nulls are dropped. */
    public function with(array $fields): static
    {
        $this->extra = array_merge($this->extra, array_filter($fields, fn ($v) => $v !== null));

        return $this;
    }

    public function toArray(): array
    {
        return array_merge([
            'type' => $this->type->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ], $this->extra);
    }
}

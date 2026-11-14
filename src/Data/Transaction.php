<?php

namespace Osaris\Monzo\Data;

use Carbon\CarbonImmutable;

class Transaction extends Data
{
    public readonly string $id;

    public readonly ?string $accountId;

    /** Minor units. Negative for debits, positive for credits. */
    public readonly int $amount;

    public readonly string $currency;

    public readonly ?int $localAmount;

    public readonly ?string $localCurrency;

    public readonly ?string $description;

    public readonly ?CarbonImmutable $created;

    /** Null while the transaction is pending. */
    public readonly ?CarbonImmutable $settled;

    public readonly ?string $category;

    public readonly ?string $notes;

    /** @var array<string, string> */
    public readonly array $metadata;

    /** True for top-ups. */
    public readonly bool $isLoad;

    /**
     * INSUFFICIENT_FUNDS, CARD_INACTIVE, CARD_BLOCKED, INVALID_CVC, OTHER...
     * Only present on declined transactions.
     */
    public readonly ?string $declineReason;

    /** Expanded merchant (when requested with expand[]=merchant). */
    public readonly ?Merchant $merchant;

    /** Merchant ID, whether expanded or not. */
    public readonly ?string $merchantId;

    protected function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->accountId = $data['account_id'] ?? null;
        $this->amount = (int) ($data['amount'] ?? 0);
        $this->currency = $data['currency'] ?? 'GBP';
        $this->localAmount = isset($data['local_amount']) ? (int) $data['local_amount'] : null;
        $this->localCurrency = $data['local_currency'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->created = static::date($data['created'] ?? null);
        $this->settled = static::date($data['settled'] ?? null);
        $this->category = $data['category'] ?? null;
        $this->notes = ($data['notes'] ?? '') !== '' ? $data['notes'] : null;
        $this->metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $this->isLoad = (bool) ($data['is_load'] ?? false);
        $this->declineReason = $data['decline_reason'] ?? null;

        $merchant = $data['merchant'] ?? null;

        $this->merchant = is_array($merchant) ? Merchant::fromArray($merchant) : null;
        $this->merchantId = is_array($merchant) ? ($merchant['id'] ?? null) : (is_string($merchant) && $merchant !== '' ? $merchant : null);
    }

    public function isDeclined(): bool
    {
        return $this->declineReason !== null;
    }

    public function isPending(): bool
    {
        return $this->settled === null && ! $this->isDeclined();
    }

    public function isSettled(): bool
    {
        return $this->settled !== null;
    }

    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    /** Amount in major units, e.g. -5.10. */
    public function value(): float
    {
        return $this->amount / 100;
    }
}

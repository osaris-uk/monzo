<?php

namespace Osaris\Monzo\Data;

class Balance extends Data
{
    /** Available balance in minor units (pennies). */
    public readonly int $balance;

    /** Balance including pots, in minor units. */
    public readonly int $totalBalance;

    /** ISO 4217 currency code. */
    public readonly string $currency;

    /** Amount spent today in minor units (negative when spending). */
    public readonly int $spendToday;

    protected function hydrate(array $data): void
    {
        $this->balance = (int) ($data['balance'] ?? 0);
        $this->totalBalance = (int) ($data['total_balance'] ?? $this->balance);
        $this->currency = $data['currency'] ?? 'GBP';
        $this->spendToday = (int) ($data['spend_today'] ?? 0);
    }

    /** Available balance in major units, e.g. 50.00. */
    public function amount(): float
    {
        return $this->balance / 100;
    }

    public function totalAmount(): float
    {
        return $this->totalBalance / 100;
    }
}

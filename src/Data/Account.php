<?php

namespace Osaris\Monzo\Data;

use Carbon\CarbonImmutable;
use Osaris\Monzo\Enums\AccountType;

class Account extends Data
{
    public readonly string $id;

    public readonly ?string $description;

    public readonly ?CarbonImmutable $created;

    /** Raw type string, e.g. uk_retail, uk_retail_joint, uk_business. */
    public readonly ?string $type;

    public readonly bool $closed;

    public readonly ?string $currency;

    public readonly ?string $accountNumber;

    public readonly ?string $sortCode;

    protected function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->description = $data['description'] ?? null;
        $this->created = static::date($data['created'] ?? null);
        $this->type = $data['type'] ?? null;
        $this->closed = (bool) ($data['closed'] ?? false);
        $this->currency = $data['currency'] ?? null;
        $this->accountNumber = $data['account_number'] ?? null;
        $this->sortCode = $data['sort_code'] ?? null;
    }

    public function accountType(): ?AccountType
    {
        return $this->type ? AccountType::tryFrom($this->type) : null;
    }

    public function isBusiness(): bool
    {
        return $this->accountType() === AccountType::Business;
    }

    public function isJoint(): bool
    {
        return $this->accountType() === AccountType::RetailJoint;
    }
}

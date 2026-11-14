<?php

namespace Osaris\Monzo\Resources;

use InvalidArgumentException;
use Osaris\Monzo\Client;
use Osaris\Monzo\Exceptions\ConfigurationException;

abstract class Resource
{
    public function __construct(
        protected Client $client,
        protected ?string $defaultAccountId = null,
    ) {}

    protected function accountId(?string $accountId): string
    {
        return $accountId
            ?? $this->defaultAccountId
            ?? throw new ConfigurationException('No account ID given and monzo.account_id (MONZO_ACCOUNT_ID) is not set.');
    }

    protected function positive(int $amount, string $what = 'Amount'): int
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("{$what} must be a positive number of minor units (pennies).");
        }

        return $amount;
    }
}

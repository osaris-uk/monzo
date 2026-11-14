<?php

namespace Osaris\Monzo\Resources;

use Illuminate\Support\Collection;
use Osaris\Monzo\Data\Account;
use Osaris\Monzo\Enums\AccountType;

class Accounts extends Resource
{
    /**
     * GET /accounts
     *
     * Leave $type empty to get every account, business accounts included.
     *
     * @return Collection<int, Account>
     */
    public function list(AccountType|string|null $type = null): Collection
    {
        $response = $this->client->get('/accounts', [
            'account_type' => $type instanceof AccountType ? $type->value : $type,
        ]);

        return Account::collect($response['accounts'] ?? []);
    }

    public function find(string $accountId): ?Account
    {
        return $this->list()->first(fn (Account $account) => $account->id === $accountId);
    }

    /** @return Collection<int, Account> */
    public function retail(): Collection
    {
        return $this->list(AccountType::Retail);
    }

    /** @return Collection<int, Account> */
    public function joint(): Collection
    {
        return $this->list(AccountType::RetailJoint);
    }

    /** @return Collection<int, Account> */
    public function business(): Collection
    {
        return $this->list()->filter(fn (Account $account) => $account->isBusiness())->values();
    }

    /** @return Collection<int, Account> */
    public function open(): Collection
    {
        return $this->list()->reject(fn (Account $account) => $account->closed)->values();
    }
}

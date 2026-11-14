<?php

namespace Osaris\Monzo\Resources;

use Osaris\Monzo\Data\Balance;

class Balances extends Resource
{
    /**
     * GET /balance
     */
    public function get(?string $accountId = null): Balance
    {
        return Balance::fromArray($this->client->get('/balance', [
            'account_id' => $this->accountId($accountId),
        ]));
    }
}

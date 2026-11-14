<?php

namespace Osaris\Monzo\Events;

use Osaris\Monzo\Data\Transaction;

/**
 * Fired for transaction.created webhooks.
 */
class TransactionCreated
{
    public function __construct(
        public readonly Transaction $transaction,
        public readonly array $payload,
    ) {}
}

<?php

namespace Osaris\Monzo\Resources;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Osaris\Monzo\Data\Transaction;

/**
 * Note: after the first 5 minutes following authentication, Monzo only lets
 * you sync the last 90 days of transactions. Pull full history straight after
 * connecting if you need it, by passing an early $since explicitly.
 */
class Transactions extends Resource
{
    public const MAX_LIMIT = 100;

    /**
     * When no $since is given, look back this many days. Kept just inside
     * Monzo's 90-day window so the request works after the first 5 minutes.
     */
    public const DEFAULT_LOOKBACK_DAYS = 89;

    /**
     * GET /transactions — a single page.
     *
     * @param  DateTimeInterface|string|null  $since  RFC 3339 timestamp or a transaction ID (default: 89 days ago)
     * @param  DateTimeInterface|string|null  $before  RFC 3339 timestamp
     * @param  int|null  $limit  1–100 (Monzo defaults to 30)
     * @return Collection<int, Transaction>
     */
    public function list(
        ?string $accountId = null,
        DateTimeInterface|string|null $since = null,
        DateTimeInterface|string|null $before = null,
        ?int $limit = null,
        bool $expandMerchant = false,
    ): Collection {
        if ($limit !== null && ($limit < 1 || $limit > self::MAX_LIMIT)) {
            throw new InvalidArgumentException('limit must be between 1 and '.self::MAX_LIMIT.'.');
        }

        $response = $this->client->get('/transactions', [
            'account_id' => $this->accountId($accountId),
            'since' => $since ?? static::defaultSince(),
            'before' => $before,
            'limit' => $limit,
            'expand' => $expandMerchant ? ['merchant'] : null,
        ]);

        return Transaction::collect($response['transactions'] ?? []);
    }

    /**
     * Every transaction in the window, fetched lazily 100 at a time.
     *
     *   Monzo::transactions()->all(since: now()->subMonth())->each(...)
     *
     * @return LazyCollection<int, Transaction>
     */
    public function all(
        ?string $accountId = null,
        DateTimeInterface|string|null $since = null,
        DateTimeInterface|string|null $before = null,
        bool $expandMerchant = false,
        int $pageSize = self::MAX_LIMIT,
    ): LazyCollection {
        $accountId = $this->accountId($accountId);
        $since ??= static::defaultSince();

        return LazyCollection::make(function () use ($accountId, $since, $before, $expandMerchant, $pageSize) {
            $cursor = $since;

            while (true) {
                $page = $this->list($accountId, $cursor, $before, $pageSize, $expandMerchant);

                foreach ($page as $transaction) {
                    yield $transaction;
                }

                if ($page->count() < $pageSize) {
                    return;
                }

                $cursor = $page->last()->id;
            }
        });
    }

    protected static function defaultSince(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays(self::DEFAULT_LOOKBACK_DAYS);
    }

    /**
     * GET /transactions/{id}
     */
    public function find(string $transactionId, bool $expandMerchant = true): Transaction
    {
        $response = $this->client->get("/transactions/{$transactionId}", [
            'expand' => $expandMerchant ? ['merchant'] : null,
        ]);

        return Transaction::fromArray($response['transaction']);
    }

    /**
     * PATCH /transactions/{id} — set metadata keys. An empty string deletes a key.
     * The "notes" key updates the transaction's visible notes.
     *
     * @param  array<string, string|null>  $metadata
     */
    public function annotate(string $transactionId, array $metadata): Transaction
    {
        $metadata = array_map(fn ($value) => $value ?? '', $metadata);

        $response = $this->client->patch("/transactions/{$transactionId}", [
            'metadata' => $metadata,
        ]);

        return Transaction::fromArray($response['transaction']);
    }

    /**
     * Set the notes shown on the transaction in the Monzo app.
     */
    public function notes(string $transactionId, ?string $notes): Transaction
    {
        return $this->annotate($transactionId, ['notes' => $notes ?? '']);
    }

    /**
     * Remove metadata keys.
     */
    public function forgetMetadata(string $transactionId, string ...$keys): Transaction
    {
        return $this->annotate($transactionId, array_fill_keys($keys, ''));
    }
}

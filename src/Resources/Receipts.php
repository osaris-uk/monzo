<?php

namespace Osaris\Monzo\Resources;

use Osaris\Monzo\Data\Receipts\Receipt;

class Receipts extends Resource
{
    /**
     * PUT /transaction-receipts — create, or update if external_id already exists.
     *
     * Returns Monzo's response body (it may include receipt_id).
     */
    public function create(Receipt|array $receipt): array
    {
        return $this->client->putJson(
            '/transaction-receipts',
            $receipt instanceof Receipt ? $receipt->toArray() : $receipt,
        );
    }

    /** Alias of create(): Monzo treats the same external_id as an update. */
    public function update(Receipt|array $receipt): array
    {
        return $this->create($receipt);
    }

    /**
     * GET /transaction-receipts — only receipts your client created can be read.
     */
    public function find(string $externalId): array
    {
        $response = $this->client->get('/transaction-receipts', ['external_id' => $externalId]);

        return $response['receipt'] ?? $response;
    }

    /**
     * DELETE /transaction-receipts
     */
    public function delete(string $externalId): void
    {
        $this->client->delete('/transaction-receipts', ['external_id' => $externalId]);
    }
}

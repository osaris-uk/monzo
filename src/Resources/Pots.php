<?php

namespace Osaris\Monzo\Resources;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Osaris\Monzo\Data\Pot;

class Pots extends Resource
{
    /**
     * GET /pots
     *
     * @return Collection<int, Pot>
     */
    public function list(?string $currentAccountId = null, bool $includeDeleted = false): Collection
    {
        $response = $this->client->get('/pots', [
            'current_account_id' => $this->accountId($currentAccountId),
        ]);

        $pots = Pot::collect($response['pots'] ?? []);

        return $includeDeleted ? $pots : $pots->reject(fn (Pot $pot) => $pot->deleted)->values();
    }

    public function find(string $potId, ?string $currentAccountId = null): ?Pot
    {
        return $this->list($currentAccountId, includeDeleted: true)
            ->first(fn (Pot $pot) => $pot->id === $potId);
    }

    public function findByName(string $name, ?string $currentAccountId = null): ?Pot
    {
        return $this->list($currentAccountId)
            ->first(fn (Pot $pot) => strcasecmp($pot->name, $name) === 0);
    }

    /**
     * PUT /pots/{id}/deposit — move money from an account into a pot.
     *
     * Pass your own $dedupeId and reuse it when retrying, so a retry can't
     * move the money twice. One is generated if you don't.
     */
    public function deposit(string $potId, int $amount, ?string $sourceAccountId = null, ?string $dedupeId = null): Pot
    {
        return Pot::fromArray($this->client->put("/pots/{$potId}/deposit", [
            'source_account_id' => $this->accountId($sourceAccountId),
            'amount' => $this->positive($amount),
            'dedupe_id' => $dedupeId ?? (string) Str::uuid(),
        ]));
    }

    /**
     * PUT /pots/{id}/withdraw — move money from a pot back into an account.
     *
     * Pots with added security can't be withdrawn from via the API.
     */
    public function withdraw(string $potId, int $amount, ?string $destinationAccountId = null, ?string $dedupeId = null): Pot
    {
        return Pot::fromArray($this->client->put("/pots/{$potId}/withdraw", [
            'destination_account_id' => $this->accountId($destinationAccountId),
            'amount' => $this->positive($amount),
            'dedupe_id' => $dedupeId ?? (string) Str::uuid(),
        ]));
    }
}

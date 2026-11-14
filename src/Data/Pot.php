<?php

namespace Osaris\Monzo\Data;

use Carbon\CarbonImmutable;

class Pot extends Data
{
    public readonly string $id;

    public readonly string $name;

    public readonly ?string $style;

    /** Minor units. */
    public readonly int $balance;

    public readonly string $currency;

    public readonly ?CarbonImmutable $created;

    public readonly ?CarbonImmutable $updated;

    public readonly bool $deleted;

    protected function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->name = $data['name'] ?? '';
        $this->style = $data['style'] ?? null;
        $this->balance = (int) ($data['balance'] ?? 0);
        $this->currency = $data['currency'] ?? 'GBP';
        $this->created = static::date($data['created'] ?? null);
        $this->updated = static::date($data['updated'] ?? null);
        $this->deleted = (bool) ($data['deleted'] ?? false);
    }

    public function amount(): float
    {
        return $this->balance / 100;
    }
}

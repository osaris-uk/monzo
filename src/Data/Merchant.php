<?php

namespace Osaris\Monzo\Data;

use Carbon\CarbonImmutable;

class Merchant extends Data
{
    public readonly string $id;

    public readonly ?string $groupId;

    public readonly ?string $name;

    public readonly ?string $logo;

    public readonly ?string $emoji;

    public readonly ?string $category;

    public readonly array $address;

    public readonly bool $online;

    public readonly bool $atm;

    public readonly ?CarbonImmutable $created;

    public readonly array $metadata;

    protected function hydrate(array $data): void
    {
        $this->id = $data['id'] ?? '';
        $this->groupId = $data['group_id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->logo = $data['logo'] ?? null;
        $this->emoji = $data['emoji'] ?? null;
        $this->category = $data['category'] ?? null;
        $this->address = is_array($data['address'] ?? null) ? $data['address'] : [];
        $this->online = (bool) ($data['online'] ?? false);
        $this->atm = (bool) ($data['atm'] ?? false);
        $this->created = static::date($data['created'] ?? null);
        $this->metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
    }
}

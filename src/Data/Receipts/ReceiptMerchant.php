<?php

namespace Osaris\Monzo\Data\Receipts;

use Illuminate\Contracts\Support\Arrayable;

class ReceiptMerchant implements Arrayable
{
    protected array $fields = [];

    public function __construct(?string $name = null)
    {
        $this->fields['name'] = $name;
    }

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    public function online(bool $online = true): static
    {
        return $this->set('online', $online);
    }

    public function phone(string $phone): static
    {
        return $this->set('phone', $phone);
    }

    public function email(string $email): static
    {
        return $this->set('email', $email);
    }

    public function storeName(string $storeName): static
    {
        return $this->set('store_name', $storeName);
    }

    public function storeAddress(string $address): static
    {
        return $this->set('store_address', $address);
    }

    public function storePostcode(string $postcode): static
    {
        return $this->set('store_postcode', $postcode);
    }

    protected function set(string $key, mixed $value): static
    {
        $this->fields[$key] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return array_filter($this->fields, fn ($value) => $value !== null);
    }
}

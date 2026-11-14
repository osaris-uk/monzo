<?php

namespace Osaris\Monzo\Data;

use ArrayAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;
use LogicException;

/**
 * Base for response objects. Documented fields are typed properties; the full
 * raw payload (including anything Monzo adds that isn't documented) is always
 * available via get(), array access, or toArray().
 */
abstract class Data implements ArrayAccess, Arrayable, JsonSerializable
{
    protected array $raw = [];

    final public function __construct(array $data)
    {
        $this->raw = $data;
        $this->hydrate($data);
    }

    abstract protected function hydrate(array $data): void;

    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    /**
     * @return Collection<int, static>
     */
    public static function collect(array $items): Collection
    {
        return Collection::make($items)
            ->map(fn (array $item) => static::fromArray($item))
            ->values();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->raw, $key, $default);
    }

    public function toArray(): array
    {
        return $this->raw;
    }

    public function jsonSerialize(): array
    {
        return $this->raw;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->raw[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->raw[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException(static::class.' is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException(static::class.' is read-only.');
    }

    protected static function date(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}

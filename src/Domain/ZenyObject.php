<?php

declare(strict_types=1);

namespace Zeny\Domain;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use LogicException;
use stdClass;

/**
 * A read-only API object: the parsed JSON body plus `lastResponse`. The body
 * is what `toArray()` and `json_encode` return, so serialising a result gives
 * the API body unchanged; the response facts stay on the side.
 *
 * @template-covariant T of array<string, mixed>
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
final class ZenyObject implements ArrayAccess, IteratorAggregate, JsonSerializable
{
    /** @param T $body */
    public function __construct(private readonly array $body, public readonly ResponseMeta $lastResponse) {}

    /** @return T */
    public function toArray(): array
    {
        return $this->body;
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && array_key_exists($offset, $this->body);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? ($this->body[$offset] ?? null) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('API objects are read-only; copy toArray() to change the data.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('API objects are read-only; copy toArray() to change the data.');
    }

    /** @return ArrayIterator<string, mixed> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->body);
    }

    /**
     * An empty body is `{}`, as the API sent it, never `[]`.
     *
     * @return T|stdClass
     */
    public function jsonSerialize(): array|stdClass
    {
        return $this->body === [] ? new stdClass : $this->body;
    }
}

<?php

declare(strict_types=1);

namespace Anser;

/**
 * One page of a list, newest first. Iterating it goes through `$data`.
 *
 * @implements \IteratorAggregate<int, array<string, mixed>>
 */
final class Page implements \IteratorAggregate, \Countable
{
    /**
     * @param list<array<string, mixed>> $data
     */
    public function __construct(public readonly array $data, public readonly bool $hasMore)
    {
    }

    /** @internal */
    public static function from(mixed $answer): self
    {
        /** @var array{data: list<array<string, mixed>>, has_more: bool} $answer */
        return new self($answer['data'], $answer['has_more']);
    }

    /** @return \ArrayIterator<int, array<string, mixed>> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }
}

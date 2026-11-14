<?php

namespace App\Support\Export;

use Illuminate\Database\Eloquent\Model;

    /** Merge {@see KeysetPageIterator} streams using shared order; identifiers must be globally unique.
     * @template TModel of Model
     */
final class KeysetMergeIterator
{
    /**
     *
     * @param  KeysetPage  $page  order + per-stream page size shared by every stream
     * @param  list<KeysetPageIterator<TModel>>  $streams
     *
     */
    public function __construct(
        private readonly KeysetPage $page,
        private readonly array $streams,
    ) {
    }

    /**
     *
     * Yield every row of every stream exactly once in the merged order.
     *
     * @return \Generator<int, TModel>
     *
     */
    public function iterate(): \Generator
    {
        $iterators = [];
        $heads = [];

        foreach (array_values($this->streams) as $index => $stream) {
            $iterator = $stream->iterate($this->page);
            $iterators[$index] = $iterator;
            $heads[$index] = $this->head($iterator);
        }

        while (true) {
            $winner = null;

            foreach ($heads as $index => $head) {
                if ($head === null) {
                    continue;
                }

                if ($winner === null || $this->compare($head, $heads[$winner]) > 0) {
                    $winner = $index;
                }
            }

            if ($winner === null) {
                return;
            }

            yield $heads[$winner];

            $iterators[$winner]->next();
            $heads[$winner] = $this->head($iterators[$winner]);
        }
    }

    /**
     *
     * @param  \Generator<int, TModel>  $iterator
     * @return TModel|null
     *
     */
    private function head(\Generator $iterator): ?Model
    {
        return $iterator->valid() ? $iterator->current() : null;
    }

    /** Compare raw selected values in the database order; positive means `$left` sorts first.
     * @param  TModel  $left
     * @param  TModel  $right
     */
    private function compare(Model $left, Model $right): int
    {
        $direction = $this->page->descending ? 1 : -1;

        $bySortValue = strcmp(
            (string) $left->getRawOriginal($this->page->sortAttribute),
            (string) $right->getRawOriginal($this->page->sortAttribute),
        );

        if ($bySortValue !== 0) {
            return $direction * $bySortValue;
        }

        return $direction * strcmp(
            (string) $left->getRawOriginal($this->page->identifierAttribute),
            (string) $right->getRawOriginal($this->page->identifierAttribute),
        );
    }
}

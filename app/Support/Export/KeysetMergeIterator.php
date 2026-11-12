<?php

namespace App\Support\Export;

use Illuminate\Database\Eloquent\Model;

/**
 * Merges N ordered keyset streams into one globally ordered sequence.
 *
 * Each stream is a {@see KeysetPageIterator} over one `form_id` (or any other
 * partition of the same ordered set) using the same sort/identifier column pair
 * and direction. The merge compares only the head row of every stream and always
 * emits the greatest `(sortValue, identifier)` for a descending order, so rows
 * with an identical sort value interleave by identifier instead of one stream
 * being drained first.
 *
 * The identifier must be globally unique for the merged order to be total; a
 * duplicate identifier across streams would make the order ambiguous.
 *
 * Memory: one look-ahead row per stream plus one page of models per stream
 * (`pageSize` of the shared {@see KeysetPage}). Callers bound the peak by dividing
 * their export page size by the stream count.
 *
 * @template TModel of Model
 */
final class KeysetMergeIterator
{
    /**
     * @param  KeysetPage  $page  order + per-stream page size shared by every stream
     * @param  list<KeysetPageIterator<TModel>>  $streams
     */
    public function __construct(
        private readonly KeysetPage $page,
        private readonly array $streams,
    ) {
    }

    /**
     * Yield every row of every stream exactly once in the merged order.
     *
     * @return \Generator<int, TModel>
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
     * @param  \Generator<int, TModel>  $iterator
     * @return TModel|null
     */
    private function head(\Generator $iterator): ?Model
    {
        return $iterator->valid() ? $iterator->current() : null;
    }

    /**
     * Compare two rows on the configured order; positive when `$left` sorts before
     * `$right`. Uses the raw selected values so the comparison sees exactly what the
     * database ordered by (not casted attributes).
     *
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
